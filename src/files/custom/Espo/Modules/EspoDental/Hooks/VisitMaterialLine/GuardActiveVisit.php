<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\VisitMaterialLine;

use Espo\Core\Exceptions\Conflict;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\Visit;
use Espo\Modules\EspoDental\Entities\VisitMaterialLine;
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
        if (!$entity instanceof VisitMaterialLine) {
            return;
        }

        $this->assertVisitIsEditable($this->resolveVisitId($entity), $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function beforeRemove(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof VisitMaterialLine) {
            return;
        }

        $this->assertVisitIsEditable($this->resolveVisitId($entity), $options);
    }

    private function resolveVisitId(VisitMaterialLine $line): ?string
    {
        if ($line->getVisitId()) {
            return $line->getVisitId();
        }

        if (!$line->getVisitServiceLineId()) {
            return null;
        }

        /** @var VisitServiceLine|null $serviceLine */
        $serviceLine = $this->entityManager->getEntityById(
            VisitServiceLine::ENTITY_TYPE,
            $line->getVisitServiceLineId()
        );

        return $serviceLine?->getVisitId();
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

        throw new Conflict('Finished visit material lines are read-only');
    }
}
