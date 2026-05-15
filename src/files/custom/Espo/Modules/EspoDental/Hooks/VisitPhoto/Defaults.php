<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\VisitPhoto;

use DateTimeImmutable;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\Visit;
use Espo\Modules\EspoDental\Entities\VisitPhoto;
use Espo\ORM\Entity;

class Defaults
{
    public static int $order = 5;

    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function beforeSave(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof VisitPhoto) {
            return;
        }

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        if (!$entity->get('recordedAt')) {
            $entity->set('recordedAt', $now);
        }

        $visitId = (string) ($entity->get('visitId') ?? '');
        if ($visitId !== '') {
            /** @var Visit|null $visit */
            $visit = $this->entityManager->getEntityById(Visit::ENTITY_TYPE, $visitId);
            if ($visit && !$entity->get('patientId')) {
                $entity->set('patientId', $visit->getPatientId());
            }
        }

        if (!$entity->get('name')) {
            $entity->set('name', $this->buildName((string) $entity->get('stage'), $now));
        }
    }

    private function buildName(string $stage, string $recordedAt): string
    {
        $stageLabel = match ($stage) {
            VisitPhoto::STAGE_DURING => 'Во время приёма',
            VisitPhoto::STAGE_AFTER => 'После приёма',
            default => 'До приёма',
        };

        return $stageLabel . ' — ' . substr($recordedAt, 0, 16);
    }
}
