<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\Appointment;

use Espo\Core\ORM\EntityManager;
use Espo\Entities\User;
use Espo\Modules\EspoDental\Entities\Appointment;
use Espo\Modules\EspoDental\Entities\PreliminaryPatient;
use Espo\ORM\Entity;

class FrontDeskFlow
{
    public static int $order = 80;

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly User $user
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

        if ($entity->isNew() && !$entity->get('bookedById')) {
            $entity->set('bookedById', $this->user->getId());
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    public function afterSave(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof Appointment) {
            return;
        }

        if ($entity->getParentType() !== PreliminaryPatient::ENTITY_TYPE || !$entity->getParentId()) {
            return;
        }

        /** @var PreliminaryPatient|null $prelim */
        $prelim = $this->entityManager->getEntityById(
            PreliminaryPatient::ENTITY_TYPE,
            (string) $entity->getParentId()
        );

        if (!$prelim || $prelim->isConverted()) {
            return;
        }

        $newStatus = match ($entity->getStatus()) {
            Appointment::STATUS_NO_SHOW => PreliminaryPatient::STATUS_NO_SHOW,
            Appointment::STATUS_CANCELLED => PreliminaryPatient::STATUS_ENTERED,
            default => PreliminaryPatient::STATUS_BOOKED,
        };

        if ($prelim->getStatus() === $newStatus) {
            return;
        }

        $prelim->set('status', $newStatus);
        $this->entityManager->saveEntity($prelim, ['skipHooks' => true]);
    }
}
