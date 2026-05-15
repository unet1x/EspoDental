<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\VisitServiceLine;

use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\Material;
use Espo\Modules\EspoDental\Entities\ServiceMaterial;
use Espo\Modules\EspoDental\Entities\VisitMaterialLine;
use Espo\Modules\EspoDental\Entities\VisitServiceLine;
use Espo\ORM\Entity;

class SyncMaterialLines
{
    public static int $order = 15;

    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function afterSave(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof VisitServiceLine) {
            return;
        }

        if (!empty($options['skipMaterialLineSync'])) {
            return;
        }

        $visitId = $entity->getVisitId();
        $serviceId = $entity->get('serviceId');

        if (!$visitId || !$serviceId) {
            return;
        }

        $normMaterialIds = $this->syncNorms($entity, (string) $visitId, (string) $serviceId);
        $this->removeStaleAutoLines($entity, $normMaterialIds);
    }

    /**
     * @return list<string>
     */
    private function syncNorms(VisitServiceLine $line, string $visitId, string $serviceId): array
    {
        /** @var iterable<ServiceMaterial> $norms */
        $norms = $this->entityManager
            ->getRDBRepository(ServiceMaterial::ENTITY_TYPE)
            ->where(['serviceId' => $serviceId])
            ->find();

        $materialIds = [];
        $lineQuantity = max(1, $line->getQuantity());

        foreach ($norms as $norm) {
            $materialId = $norm->get('materialId');
            if (!$materialId) {
                continue;
            }

            $materialIds[] = (string) $materialId;

            /** @var Material|null $material */
            $material = $this->entityManager->getEntityById(Material::ENTITY_TYPE, (string) $materialId);
            if (!$material) {
                continue;
            }

            $plannedQuantity = round((float) $norm->get('quantity') * $lineQuantity, 4);

            /** @var VisitMaterialLine|null $materialLine */
            $materialLine = $this->entityManager
                ->getRDBRepository(VisitMaterialLine::ENTITY_TYPE)
                ->where([
                    'visitServiceLineId' => $line->getId(),
                    'materialId' => $materialId,
                ])
                ->findOne();

            if ($materialLine) {
                $previousPlanned = (float) $materialLine->get('plannedQuantity');
                $previousQuantity = (float) $materialLine->get('quantity');
                $wasNotManuallyAdjusted = abs($previousQuantity - $previousPlanned) < 0.0001;
            } else {
                /** @var VisitMaterialLine $materialLine */
                $materialLine = $this->entityManager->getNewEntity(VisitMaterialLine::ENTITY_TYPE);
                $materialLine->set('quantity', $plannedQuantity);
                $materialLine->set('isAutoCreated', true);
                $wasNotManuallyAdjusted = true;
            }

            $materialLine->set('visitId', $visitId);
            $materialLine->set('visitServiceLineId', $line->getId());
            $materialLine->set('serviceId', $serviceId);
            $materialLine->set('materialId', $materialId);
            $materialLine->set('plannedQuantity', $plannedQuantity);
            $materialLine->set('unit', (string) $material->get('unit'));
            $materialLine->set('unitPrice', (float) $material->get('price'));
            $materialLine->set('unitPriceCurrency', (string) ($material->get('priceCurrency') ?: 'RUB'));
            $materialLine->set('name', (string) $material->get('name'));

            if ($wasNotManuallyAdjusted) {
                $materialLine->set('quantity', $plannedQuantity);
            }

            $this->entityManager->saveEntity($materialLine);
        }

        return array_values(array_unique($materialIds));
    }

    /**
     * @param list<string> $currentMaterialIds
     */
    private function removeStaleAutoLines(VisitServiceLine $line, array $currentMaterialIds): void
    {
        /** @var iterable<VisitMaterialLine> $lines */
        $lines = $this->entityManager
            ->getRDBRepository(VisitMaterialLine::ENTITY_TYPE)
            ->where(['visitServiceLineId' => $line->getId()])
            ->find();

        foreach ($lines as $materialLine) {
            if (!$materialLine->get('isAutoCreated')) {
                continue;
            }

            if (in_array((string) $materialLine->getMaterialId(), $currentMaterialIds, true)) {
                continue;
            }

            $this->entityManager->removeEntity($materialLine);
        }
    }
}
