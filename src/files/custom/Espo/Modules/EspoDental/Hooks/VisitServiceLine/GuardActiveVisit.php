<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\VisitServiceLine;

use Espo\Core\Exceptions\Conflict;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\Visit;
use Espo\Modules\EspoDental\Entities\VisitServiceLine;
use Espo\ORM\Entity;

class GuardActiveVisit
{
    public static int $order = 1;

    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function beforeSave(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof VisitServiceLine) {
            return;
        }

        $this->assertVisitIsEditable($entity->getVisitId(), $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function beforeRemove(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof VisitServiceLine) {
            return;
        }

        $this->assertVisitIsEditable($entity->getVisitId(), $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function assertVisitIsEditable(?string $visitId, array $options): void
    {
        if (!empty($options['espodentalAllowFinishedVisitCorrection'])) {
            return;
        }

        if (!$visitId) {
            return;
        }

        /** @var Visit|null $visit */
        $visit = $this->entityManager->getEntityById(Visit::ENTITY_TYPE, $visitId);

        if (!$visit || $visit->getStatus() === Visit::STATUS_IN_PROGRESS) {
            return;
        }

        throw new Conflict('Finished visit service lines are read-only');
    }
}
