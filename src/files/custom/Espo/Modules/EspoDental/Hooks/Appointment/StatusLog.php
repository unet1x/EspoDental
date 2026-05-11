<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\Appointment;

use DateTimeImmutable;
use Espo\Core\ORM\EntityManager;
use Espo\Entities\User;
use Espo\Modules\EspoDental\Entities\Appointment;
use Espo\Modules\EspoDental\Entities\AppointmentStatusLog as StatusLogEntity;
use Espo\ORM\Entity;

class StatusLog
{
    public static int $order = 90;

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly User $user
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function afterSave(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof Appointment) {
            return;
        }
        if (!empty($options['skipStatusLog'])) {
            return;
        }

        $newStatus = (string) $entity->getStatus();
        $oldStatus = (string) ($entity->getFetched('status') ?? '');

        if ($entity->isNew()) {
            $this->writeLog($entity, '', $newStatus, null);
            return;
        }

        if ($newStatus === $oldStatus) {
            return;
        }

        $durationInPrevious = $this->computeDurationFromLastLog($entity);

        $this->writeLog($entity, $oldStatus, $newStatus, $durationInPrevious);
    }

    private function writeLog(
        Appointment $appointment,
        string $fromStatus,
        string $toStatus,
        ?int $durationInPrevious
    ): void {
        /** @var StatusLogEntity $log */
        $log = $this->entityManager->getNewEntity(StatusLogEntity::ENTITY_TYPE);
        $log->set('name', $appointment->get('name') . ' → ' . $toStatus);
        $log->set('appointmentId', $appointment->getId());
        $log->set('fromStatus', $fromStatus);
        $log->set('toStatus', $toStatus);
        $log->set('changedAt', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
        $log->set('changedById', $this->user->getId());
        if ($durationInPrevious !== null) {
            $log->set('durationInPrevious', $durationInPrevious);
        }
        $this->entityManager->saveEntity($log, ['skipHooks' => true]);
    }

    private function computeDurationFromLastLog(Appointment $appointment): ?int
    {
        /** @var StatusLogEntity|null $last */
        $last = $this->entityManager
            ->getRDBRepository(StatusLogEntity::ENTITY_TYPE)
            ->where(['appointmentId' => $appointment->getId()])
            ->order('changedAt', 'DESC')
            ->findOne();

        if (!$last || !$last->get('changedAt')) {
            return null;
        }

        try {
            $changedAt = new DateTimeImmutable((string) $last->get('changedAt'));
            $now = new DateTimeImmutable();
            return max(0, $now->getTimestamp() - $changedAt->getTimestamp());
        } catch (\Exception) {
            return null;
        }
    }
}
