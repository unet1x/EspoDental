<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\PreliminaryPatient;

use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config;
use Espo\Modules\EspoDental\Entities\Clinic;
use Espo\Modules\EspoDental\Entities\PreliminaryPatient;
use Espo\ORM\Entity;

class Defaults
{
    public static int $order = 10;

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
        if (!$entity instanceof PreliminaryPatient) {
            return;
        }

        if (!$entity->get('status')) {
            $entity->set('status', PreliminaryPatient::STATUS_ENTERED);
        }

        if ($entity->getClinicId()) {
            return;
        }

        $clinicId = $this->resolveDefaultClinicId();

        if ($clinicId) {
            $entity->set('clinicId', $clinicId);
        }
    }

    private function resolveDefaultClinicId(): ?string
    {
        $configuredId = (string) $this->config->get('espoDentalDefaultClinicId', '');

        if ($configuredId !== '') {
            /** @var Clinic|null $clinic */
            $clinic = $this->entityManager->getEntityById(Clinic::ENTITY_TYPE, $configuredId);

            if ($clinic && $clinic->isActive()) {
                return $configuredId;
            }
        }

        $activeClinics = $this->entityManager
            ->getRDBRepository(Clinic::ENTITY_TYPE)
            ->where(['isActive' => true])
            ->find();

        $singleId = null;
        $count = 0;

        foreach ($activeClinics as $clinic) {
            $count++;

            if ($count > 1) {
                return null;
            }

            $singleId = (string) $clinic->getId();
        }

        return $count === 1 ? $singleId : null;
    }
}
