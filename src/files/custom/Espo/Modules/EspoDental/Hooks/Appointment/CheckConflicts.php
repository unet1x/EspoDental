<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\Appointment;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config;
use Espo\Modules\EspoDental\Entities\Appointment;
use Espo\Modules\EspoDental\Entities\Clinic;
use Espo\Modules\EspoDental\Tools\DoctorShiftAvailability;
use Espo\ORM\Entity;

class CheckConflicts
{
    public static int $order = 9;

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Config $config
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function beforeSave(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof Appointment) {
            return;
        }
        if (!empty($options['skipConflictCheck'])) {
            return;
        }

        $status = $entity->getStatus();
        if (
            in_array($status, [
            Appointment::STATUS_CANCELLED,
            Appointment::STATUS_FINISHED,
            Appointment::STATUS_NO_SHOW,
            ], true)
        ) {
            return;
        }

        $dateStart = $entity->getDateStart();
        $dateEnd = $entity->getDateEnd();

        if (!$dateStart || !$dateEnd) {
            return;
        }

        $this->guardDoctorShift($entity, $dateStart, $dateEnd);

        $doctorId = $entity->getDoctorId();
        $cabinetId = $entity->getCabinetId();
        $parentType = $entity->getParentType();
        $parentId = $entity->getParentId();

        $conflict = $this->findConflict(
            $entity->getId(),
            $doctorId,
            $cabinetId,
            $parentType,
            $parentId,
            $dateStart,
            $dateEnd
        );

        if ($conflict) {
            $by = $conflict['by'];
            $message = match ($by) {
                'doctor' => 'Doctor is already booked at this time.',
                'cabinet' => 'Cabinet is already booked at this time.',
                'patient' => 'Patient is already booked at this time.',
                default => 'Time slot conflict.',
            };
            throw new Conflict($message);
        }
    }

    private function guardDoctorShift(Appointment $appointment, string $dateStart, string $dateEnd): void
    {
        $doctorId = $appointment->getDoctorId();

        if (!$doctorId) {
            return;
        }

        $range = $this->appointmentLocalDayRange($appointment);

        if ($range === null) {
            return;
        }

        $shiftAvailability = new DoctorShiftAvailability($this->entityManager);
        $schedule = $shiftAvailability->loadForRange(
            $doctorId,
            $range[0],
            $range[1],
            $appointment->getClinicId()
        );

        if (!$schedule['hasAvailability'] && $schedule['closed'] === []) {
            return;
        }

        try {
            $startTs = $this->createUtcDateTime($dateStart)->getTimestamp();
            $endTs = $this->createUtcDateTime($dateEnd)->getTimestamp();
        } catch (\Exception) {
            return;
        }

        $result = $shiftAvailability->evaluateSlot(
            $schedule,
            $startTs,
            $endTs,
            $appointment->getCabinetId()
        );

        if (!$result['available']) {
            throw new Conflict($result['reason'] ?? 'Doctor is not available at this time.');
        }
    }

    /**
     * @return ?array{by: string, id: string}
     */
    private function findConflict(
        ?string $excludeId,
        ?string $doctorId,
        ?string $cabinetId,
        ?string $parentType,
        ?string $parentId,
        string $dateStart,
        string $dateEnd
    ): ?array {
        if (!$doctorId && !$cabinetId && (!$parentType || !$parentId)) {
            return null;
        }

        $or = [];
        if ($doctorId) {
            $or[] = ['doctorId' => $doctorId];
        }
        if ($cabinetId) {
            $or[] = ['cabinetId' => $cabinetId];
        }
        if ($parentType && $parentId) {
            $or[] = ['parentType' => $parentType, 'parentId' => $parentId];
        }

        $where = [
            'OR' => $or,
            'status' => Appointment::BLOCKING_STATUSES,
            'dateStart<' => $dateEnd,
            'dateEnd>' => $dateStart,
            'deleted' => false,
        ];

        if ($excludeId) {
            $where['id!='] = $excludeId;
        }

        /** @var Appointment|null $existing */
        $existing = $this->entityManager
            ->getRDBRepository(Appointment::ENTITY_TYPE)
            ->where($where)
            ->findOne();

        if (!$existing) {
            return null;
        }

        if ($doctorId && $existing->getDoctorId() === $doctorId) {
            $by = 'doctor';
        } elseif ($cabinetId && $existing->getCabinetId() === $cabinetId) {
            $by = 'cabinet';
        } else {
            $by = 'patient';
        }

        return ['by' => $by, 'id' => (string) $existing->getId()];
    }

    /**
     * @return ?array{string, string}
     */
    private function appointmentLocalDayRange(Appointment $appointment): ?array
    {
        $dateStart = (string) $appointment->getDateStart();
        $dateEnd = (string) $appointment->getDateEnd();

        if ($dateStart === '' || $dateEnd === '') {
            return null;
        }

        try {
            $utc = new DateTimeZone('UTC');
            $timeZone = $this->resolveTimeZone($appointment->getClinicId());
            $startLocal = $this->createUtcDateTime($dateStart)
                ->setTimezone($timeZone)
                ->setTime(0, 0);
            $endLocal = $this->createUtcDateTime($dateEnd)
                ->setTimezone($timeZone)
                ->setTime(0, 0)
                ->modify('+1 day');

            return [
                $startLocal->setTimezone($utc)->format('Y-m-d H:i:s'),
                $endLocal->setTimezone($utc)->format('Y-m-d H:i:s'),
            ];
        } catch (\Exception) {
            return null;
        }
    }

    private function createUtcDateTime(string $value): DateTimeImmutable
    {
        $utc = new DateTimeZone('UTC');
        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $utc);

        if ($date instanceof DateTimeImmutable) {
            return $date;
        }

        return new DateTimeImmutable($value, $utc);
    }

    private function resolveTimeZone(?string $clinicId = null): DateTimeZone
    {
        if ($clinicId) {
            /** @var Clinic|null $clinic */
            $clinic = $this->entityManager->getEntityById(Clinic::ENTITY_TYPE, $clinicId);

            if ($clinic) {
                $timeZone = (string) ($clinic->get('timezone') ?: '');

                if ($timeZone !== '') {
                    return $this->buildTimeZone($timeZone);
                }
            }
        }

        return $this->buildTimeZone((string) ($this->config->get('timeZone') ?: 'UTC'));
    }

    private function buildTimeZone(string $timeZone): DateTimeZone
    {
        try {
            return new DateTimeZone($timeZone);
        } catch (\Exception) {
            return new DateTimeZone('UTC');
        }
    }
}
