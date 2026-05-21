<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Tools;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\DoctorShift;

class DoctorShiftAvailability
{
    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    /**
     * @return array{
     *     hasAvailability: bool,
     *     availability: list<array{start: int, end: int, cabinetId: ?string, assistantId: ?string}>,
     *     closed: list<array{start: int, end: int, cabinetId: ?string}>
     * }
     */
    public function loadForRange(
        string $doctorId,
        string $dateStart,
        string $dateEnd,
        ?string $clinicId = null
    ): array {
        $where = [
            'deleted' => false,
            'doctorId' => $doctorId,
            'status' => DoctorShift::STATUS_ACTIVE,
            'dateStart<' => $dateEnd,
            'dateEnd>' => $dateStart,
        ];

        if ($clinicId) {
            $where['clinicId'] = $clinicId;
        }

        /** @var iterable<DoctorShift> $shifts */
        $shifts = $this->entityManager
            ->getRDBRepository(DoctorShift::ENTITY_TYPE)
            ->where($where)
            ->order('dateStart', 'ASC')
            ->find();

        $availability = [];
        $closed = [];

        foreach ($shifts as $shift) {
            $start = $this->toUtcTimestamp((string) $shift->getDateStart());
            $end = $this->toUtcTimestamp((string) $shift->getDateEnd());

            if ($start === null || $end === null || $end <= $start) {
                continue;
            }

            $cabinetId = $this->normalizeId($shift->getCabinetId());

            $type = $shift->getType();

            if ($type === DoctorShift::TYPE_CLOSED) {
                $closed[] = [
                    'start' => $start,
                    'end' => $end,
                    'cabinetId' => $cabinetId,
                ];

                continue;
            }

            if (!in_array($type, [DoctorShift::TYPE_REGULAR, DoctorShift::TYPE_ADDITIONAL], true)) {
                continue;
            }

            $availability[] = [
                'start' => $start,
                'end' => $end,
                'cabinetId' => $cabinetId,
                'assistantId' => $this->normalizeId($shift->getAssistantId()),
            ];
        }

        return [
            'hasAvailability' => $availability !== [] || $this->hasAnyAvailability($doctorId, $clinicId),
            'availability' => $availability,
            'closed' => $closed,
        ];
    }

    /**
     * @param array{
     *     hasAvailability: bool,
     *     availability: list<array{start: int, end: int, cabinetId: ?string, assistantId: ?string}>,
     *     closed: list<array{start: int, end: int, cabinetId: ?string}>
     * } $schedule
     * @return array{available: bool, assistantId: ?string, reason: ?string}
     */
    public function evaluateSlot(
        array $schedule,
        int $start,
        int $end,
        ?string $cabinetId = null
    ): array {
        $cabinetId = $this->normalizeId($cabinetId);

        foreach ($schedule['closed'] as $closed) {
            if (!$this->appliesToCabinet($closed['cabinetId'], $cabinetId)) {
                continue;
            }

            if ($start < $closed['end'] && $end > $closed['start']) {
                return [
                    'available' => false,
                    'assistantId' => null,
                    'reason' => 'Doctor shift is closed for this time.',
                ];
            }
        }

        if (!$schedule['hasAvailability']) {
            return [
                'available' => true,
                'assistantId' => null,
                'reason' => null,
            ];
        }

        foreach ($schedule['availability'] as $availability) {
            if (!$this->appliesToCabinet($availability['cabinetId'], $cabinetId)) {
                continue;
            }

            if ($start >= $availability['start'] && $end <= $availability['end']) {
                return [
                    'available' => true,
                    'assistantId' => $availability['assistantId'],
                    'reason' => null,
                ];
            }
        }

        return [
            'available' => false,
            'assistantId' => null,
            'reason' => 'Doctor has no active shift for this time.',
        ];
    }

    private function normalizeId(?string $id): ?string
    {
        $id = trim((string) $id);

        return $id !== '' ? $id : null;
    }

    private function appliesToCabinet(?string $shiftCabinetId, ?string $cabinetId): bool
    {
        return $shiftCabinetId === null || $cabinetId === null || $shiftCabinetId === $cabinetId;
    }

    private function hasAnyAvailability(string $doctorId, ?string $clinicId): bool
    {
        $where = [
            'deleted' => false,
            'doctorId' => $doctorId,
            'status' => DoctorShift::STATUS_ACTIVE,
        ];

        if ($clinicId) {
            $where['clinicId'] = $clinicId;
        }

        /** @var iterable<DoctorShift> $shifts */
        $shifts = $this->entityManager
            ->getRDBRepository(DoctorShift::ENTITY_TYPE)
            ->where($where)
            ->find();

        foreach ($shifts as $shift) {
            if (in_array($shift->getType(), [DoctorShift::TYPE_REGULAR, DoctorShift::TYPE_ADDITIONAL], true)) {
                return true;
            }
        }

        return false;
    }

    private function toUtcTimestamp(string $value): ?int
    {
        if ($value === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->getTimestamp();
        } catch (\Exception) {
            return null;
        }
    }
}
