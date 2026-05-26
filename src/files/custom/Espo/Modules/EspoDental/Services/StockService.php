<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\Material;
use Espo\Modules\EspoDental\Entities\ServiceMaterial;
use Espo\Modules\EspoDental\Entities\StockMovement;
use Espo\Modules\EspoDental\Entities\Visit;
use Espo\Modules\EspoDental\Entities\VisitMaterialLine;
use Espo\Modules\EspoDental\Entities\VisitServiceLine;

class StockService
{
    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    /**
     * @return int Number of StockMovement rows created.
     */
    public function consumeForVisit(Visit $visit): int
    {
        $clinicId = $visit->get('clinicId');
        if (!$clinicId) {
            return 0;
        }

        $materialLineCount = (int) $this->entityManager
            ->getRDBRepository(VisitMaterialLine::ENTITY_TYPE)
            ->where(['visitId' => $visit->getId()])
            ->count();

        if ($materialLineCount > 0) {
            return $this->consumePreparedMaterialLines($visit, (string) $clinicId);
        }

        return $this->consumeLegacyServiceNorms($visit, (string) $clinicId);
    }

    private function consumePreparedMaterialLines(Visit $visit, string $clinicId): int
    {
        /** @var iterable<VisitMaterialLine> $lines */
        $lines = $this->entityManager
            ->getRDBRepository(VisitMaterialLine::ENTITY_TYPE)
            ->where(['visitId' => $visit->getId()])
            ->find();

        $created = 0;
        foreach ($lines as $line) {
            if ($line->getQuantity() <= 0) {
                continue;
            }

            $existing = $this->entityManager
                ->getRDBRepository(StockMovement::ENTITY_TYPE)
                ->where([
                    'sourceVisitMaterialLineId' => $line->getId(),
                    'type' => StockMovement::TYPE_CONSUMPTION,
                ])
                ->findOne();

            if ($existing) {
                continue;
            }

            /** @var Material|null $material */
            $material = $this->entityManager->getEntityById(Material::ENTITY_TYPE, (string) $line->getMaterialId());
            if (!$material) {
                continue;
            }

            /** @var StockMovement $mv */
            $mv = $this->entityManager->getNewEntity(StockMovement::ENTITY_TYPE);
            $mv->set('materialId', $line->getMaterialId());
            $mv->set('clinicId', $clinicId);
            $mv->set('type', StockMovement::TYPE_CONSUMPTION);
            $mv->set('quantity', $line->getQuantity());
            $mv->set('unit', (string) ($line->get('unit') ?: $material->getConsumptionUnit()));
            $mv->set('unitPrice', $line->getUnitPrice());
            $mv->set('performedAt', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
            $mv->set('performedById', $visit->get('doctorId'));
            $mv->set('sourceVisitId', $visit->getId());
            $mv->set('sourceVisitServiceLineId', $line->getVisitServiceLineId());
            $mv->set('sourceVisitMaterialLineId', $line->getId());
            $mv->set('reason', 'Auto-consumption from prepared visit material line');
            $this->entityManager->saveEntity($mv);
            $created++;
        }

        return $created;
    }

    private function consumeLegacyServiceNorms(Visit $visit, string $clinicId): int
    {
        /** @var iterable<VisitServiceLine> $lines */
        $lines = $this->entityManager
            ->getRDBRepository(VisitServiceLine::ENTITY_TYPE)
            ->where(['visitId' => $visit->getId()])
            ->find();

        $created = 0;
        foreach ($lines as $line) {
            $serviceId = $line->get('serviceId');
            if (!$serviceId) {
                continue;
            }

            $existing = $this->entityManager
                ->getRDBRepository(StockMovement::ENTITY_TYPE)
                ->where([
                    'sourceVisitServiceLineId' => $line->getId(),
                    'type' => StockMovement::TYPE_CONSUMPTION,
                ])
                ->findOne();
            if ($existing) {
                continue;
            }

            /** @var iterable<ServiceMaterial> $norms */
            $norms = $this->entityManager
                ->getRDBRepository(ServiceMaterial::ENTITY_TYPE)
                ->where(['serviceId' => $serviceId])
                ->find();

            foreach ($norms as $norm) {
                $materialId = $norm->get('materialId');
                if (!$materialId) {
                    continue;
                }
                $perUnit = $norm->getQuantity();
                $qty = $perUnit * max(1, $line->getQuantity());
                if ($qty <= 0) {
                    continue;
                }

                /** @var Material|null $material */
                $material = $this->entityManager->getEntityById(Material::ENTITY_TYPE, $materialId);
                if (!$material) {
                    continue;
                }

                /** @var StockMovement $mv */
                $mv = $this->entityManager->getNewEntity(StockMovement::ENTITY_TYPE);
                $mv->set('materialId', $materialId);
                $mv->set('clinicId', $clinicId);
                $mv->set('type', StockMovement::TYPE_CONSUMPTION);
                $mv->set('quantity', $qty);
                $mv->set('unit', $material->getConsumptionUnit());
                $mv->set('unitPrice', (float) $material->get('price'));
                $mv->set('performedAt', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
                $mv->set('performedById', $visit->get('doctorId'));
                $mv->set('sourceVisitId', $visit->getId());
                $mv->set('sourceVisitServiceLineId', $line->getId());
                $mv->set('reason', 'Auto-consumption for visit service line');
                $this->entityManager->saveEntity($mv);
                $created++;
            }
        }
        return $created;
    }
}
