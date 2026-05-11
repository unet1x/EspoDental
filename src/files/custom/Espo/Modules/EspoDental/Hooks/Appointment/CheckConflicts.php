<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\Appointment;

use Espo\Core\Exceptions\Conflict;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\Appointment;
use Espo\ORM\Entity;

class CheckConflicts
{
    public static int $order = 9;

    public function __construct(private readonly EntityManager $entityManager)
    {
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

        $doctorId = $entity->getDoctorId();
        $cabinetId = $entity->getCabinetId();

        $conflict = $this->findConflict(
            $entity->getId(),
            $doctorId,
            $cabinetId,
            $dateStart,
            $dateEnd
        );

        if ($conflict) {
            $by = $conflict['by'];
            $message = match ($by) {
                'doctor' => 'Doctor is already booked at this time.',
                'cabinet' => 'Cabinet is already booked at this time.',
                default => 'Time slot conflict.',
            };
            throw new Conflict($message);
        }
    }

    /**
     * @return ?array{by: string, id: string}
     */
    private function findConflict(
        ?string $excludeId,
        ?string $doctorId,
        ?string $cabinetId,
        string $dateStart,
        string $dateEnd
    ): ?array {
        if (!$doctorId && !$cabinetId) {
            return null;
        }

        $or = [];
        if ($doctorId) {
            $or[] = ['doctorId' => $doctorId];
        }
        if ($cabinetId) {
            $or[] = ['cabinetId' => $cabinetId];
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

        $by = ($doctorId && $existing->getDoctorId() === $doctorId) ? 'doctor' : 'cabinet';

        return ['by' => $by, 'id' => (string) $existing->getId()];
    }
}
