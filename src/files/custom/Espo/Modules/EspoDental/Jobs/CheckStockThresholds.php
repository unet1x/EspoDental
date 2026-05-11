<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Jobs;

use DateTimeImmutable;
use Espo\Core\Job\Job;
use Espo\Core\Job\Job\Data;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\LowStockAlert;
use Espo\Modules\EspoDental\Entities\Material;

class CheckStockThresholds implements Job
{
    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    public function run(Data $data): void
    {
        /** @var iterable<Material> $materials */
        $materials = $this->entityManager
            ->getRDBRepository(Material::ENTITY_TYPE)
            ->where(['isActive' => true])
            ->find();

        foreach ($materials as $material) {
            $level = $material->computeLevel();
            if ($level === Material::LEVEL_OK) {
                $this->resolveOpenAlerts($material);
                continue;
            }
            $this->upsertAlert($material, $level);
        }
    }

    private function upsertAlert(Material $material, string $level): void
    {
        $existing = $this->entityManager
            ->getRDBRepository(LowStockAlert::ENTITY_TYPE)
            ->where([
                'materialId' => $material->getId(),
                'status' => LowStockAlert::STATUS_OPEN,
            ])
            ->findOne();

        if ($existing) {
            if ((string) $existing->get('level') !== $level) {
                $existing->set('level', $level);
                $existing->set('currentStock', $material->getCurrentStock());
                $existing->set('threshold', $this->thresholdFor($material, $level));
                $this->entityManager->saveEntity($existing);
            }
            return;
        }

        $alert = $this->entityManager->getNewEntity(LowStockAlert::ENTITY_TYPE);
        $alert->set('name', $this->buildAlertName($material, $level));
        $alert->set('materialId', $material->getId());
        $alert->set('level', $level);
        $alert->set('currentStock', $material->getCurrentStock());
        $alert->set('threshold', $this->thresholdFor($material, $level));
        $alert->set('status', LowStockAlert::STATUS_OPEN);
        $alert->set('raisedAt', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
        $alert->set('assignedUserId', $material->get('assignedUserId'));
        $this->entityManager->saveEntity($alert);
    }

    private function resolveOpenAlerts(Material $material): void
    {
        /** @var iterable<LowStockAlert> $open */
        $open = $this->entityManager
            ->getRDBRepository(LowStockAlert::ENTITY_TYPE)
            ->where([
                'materialId' => $material->getId(),
                'status' => LowStockAlert::STATUS_OPEN,
            ])
            ->find();

        foreach ($open as $alert) {
            $alert->set('status', LowStockAlert::STATUS_RESOLVED);
            $alert->set('resolvedAt', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
            $this->entityManager->saveEntity($alert);
        }
    }

    private function thresholdFor(Material $material, string $level): float
    {
        return match ($level) {
            Material::LEVEL_CRITICAL => $material->getCriticalStock(),
            Material::LEVEL_LOW => $material->getMinStock(),
            default => 0.0,
        };
    }

    private function buildAlertName(Material $material, string $level): string
    {
        return $level . ' · ' . (string) $material->get('name')
            . ' · ' . $material->getCurrentStock() . ' ' . (string) $material->get('unit');
    }
}
