<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\InventoryWarehouse;
use Espo\Modules\EspoDental\Entities\InventoryStockLot;
use Espo\Modules\EspoDental\Entities\LowStockAlert;
use Espo\Modules\EspoDental\Entities\Material;
use Espo\Modules\EspoDental\Entities\StockMovement;
use Espo\ORM\Entity;

class InventoryService
{
    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getWorkspace(?string $clinicId = null, ?string $warehouseId = null, int $limit = 20): array
    {
        $clinicId = $this->normalizeOptionalId($clinicId);
        $warehouseId = $this->normalizeOptionalId($warehouseId);
        $limit = max(1, min(50, $limit));
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        $expiringBefore = (new DateTimeImmutable('today +30 days'))->format('Y-m-d');
        $warehouses = $this->getWarehouseRows($clinicId);

        if ($warehouseId === null && $warehouses !== []) {
            $warehouseId = (string) $warehouses[0]['id'];
        }

        return [
            'filters' => [
                'clinicId' => $clinicId,
                'warehouseId' => $warehouseId,
            ],
            'summary' => $this->getWorkspaceSummary($warehouses, $clinicId, $today, $expiringBefore),
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $warehouseId,
            'stockLots' => $this->getStockLotRows($warehouseId, $limit, $today, $expiringBefore),
            'lowStockRows' => $this->getLowStockRows($clinicId, $limit),
            'expiringLots' => $this->getExpiringLotRows($clinicId, $limit, $today, $expiringBefore),
            'futureOrderCandidates' => $this->getFutureOrderCandidates($clinicId, $limit),
            'cabinetIssueRows' => $this->getCabinetIssueRows($clinicId, $limit),
            'recentMovements' => $this->getRecentMovements($clinicId, $warehouseId, $limit),
        ];
    }

    /**
     * @return array{lots: list<array<string, mixed>>, remainingQuantity: float}
     */
    public function planFefoConsumption(string $warehouseId, string $materialId, float $quantity): array
    {
        $remaining = max(0.0, $quantity);
        $planned = [];

        foreach ($this->getFefoLots($warehouseId, $materialId) as $lot) {
            if ($remaining <= 0.0) {
                break;
            }

            $available = $lot->getQuantityInPurchasingUnits();
            if ($available <= 0.0) {
                continue;
            }

            $consume = min($available, $remaining);
            $planned[] = [
                'stockLotId' => (string) $lot->getId(),
                'warehouseId' => (string) $lot->get('warehouseId'),
                'materialId' => (string) $lot->get('materialId'),
                'lotNumber' => (string) ($lot->get('lotNumber') ?? ''),
                'expiresAt' => (string) ($lot->get('expiresAt') ?? ''),
                'quantity' => round($consume, 4),
            ];
            $remaining = round($remaining - $consume, 4);
        }

        return [
            'lots' => $planned,
            'remainingQuantity' => max(0.0, $remaining),
        ];
    }

    public function assertReceiptExpiration(Material $material, ?string $expiresAt): void
    {
        $trackExpiration = (bool) ($material->get('trackExpiration') ?? $material->get('expiryControl') ?? false);

        if ($trackExpiration && trim((string) $expiresAt) === '') {
            throw new BadRequest('Expiration date is required for this material');
        }
    }

    /**
     * @return list<InventoryStockLot>
     */
    private function getFefoLots(string $warehouseId, string $materialId): array
    {
        /** @var iterable<InventoryStockLot> $lots */
        $lots = $this->entityManager
            ->getRDBRepository(InventoryStockLot::ENTITY_TYPE)
            ->where([
                'deleted' => false,
                'warehouseId' => $warehouseId,
                'materialId' => $materialId,
            ])
            ->order('expiresAt', 'ASC')
            ->find();

        $rows = [];
        foreach ($lots as $lot) {
            if ($lot->getQuantityInPurchasingUnits() > 0.0) {
                $rows[] = $lot;
            }
        }

        usort($rows, function (InventoryStockLot $a, InventoryStockLot $b): int {
            $aExpires = (string) ($a->get('expiresAt') ?: '9999-12-31');
            $bExpires = (string) ($b->get('expiresAt') ?: '9999-12-31');

            if ($aExpires !== $bExpires) {
                return $aExpires <=> $bExpires;
            }

            $aReceived = (string) ($a->get('receivedAt') ?: '9999-12-31');
            $bReceived = (string) ($b->get('receivedAt') ?: '9999-12-31');

            return $aReceived <=> $bReceived;
        });

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getWarehouseRows(?string $clinicId): array
    {
        $where = [
            'deleted' => false,
            'isActive' => true,
        ];

        if ($clinicId !== null) {
            $where['clinicId'] = $clinicId;
        }

        /** @var iterable<InventoryWarehouse> $warehouses */
        $warehouses = $this->entityManager
            ->getRDBRepository(InventoryWarehouse::ENTITY_TYPE)
            ->where($where)
            ->order('name', 'ASC')
            ->find();

        $rows = [];

        foreach ($warehouses as $warehouse) {
            $warehouseId = (string) $warehouse->getId();

            if ($warehouseId === '') {
                continue;
            }

            $rows[] = [
                'id' => $warehouseId,
                'name' => (string) ($warehouse->get('name') ?: $warehouseId),
                'warehouseType' => (string) ($warehouse->get('warehouseType') ?: InventoryWarehouse::TYPE_MAIN),
                'clinicId' => (string) ($warehouse->get('clinicId') ?? ''),
                'clinicName' => (string) ($warehouse->get('clinicName') ?? ''),
                'cabinetId' => (string) ($warehouse->get('cabinetId') ?? ''),
                'cabinetName' => (string) ($warehouse->get('cabinetName') ?? ''),
                'responsibleUserId' => (string) ($warehouse->get('responsibleUserId') ?? ''),
                'responsibleUserName' => (string) ($warehouse->get('responsibleUserName') ?? ''),
                'nextInventoryDueAt' => (string) ($warehouse->get('nextInventoryDueAt') ?? ''),
                'lastInventoryAt' => (string) ($warehouse->get('lastInventoryAt') ?? ''),
                'lotCount' => $this->countLotsForWarehouse($warehouseId),
                'stockValue' => $this->sumWarehouseStockValue($warehouseId),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $warehouses
     * @return array<string, mixed>
     */
    private function getWorkspaceSummary(array $warehouses, ?string $clinicId, string $today, string $expiringBefore): array
    {
        return [
            'warehouseCount' => count($warehouses),
            'mainWarehouseCount' => count(array_filter(
                $warehouses,
                static fn (array $row): bool => $row['warehouseType'] === InventoryWarehouse::TYPE_MAIN
            )),
            'cabinetWarehouseCount' => count(array_filter(
                $warehouses,
                static fn (array $row): bool => $row['warehouseType'] === InventoryWarehouse::TYPE_SATELLITE
            )),
            'lowStockCount' => count($this->getLowStockRows($clinicId, 200)),
            'expiringLotCount' => count($this->getExpiringLotRows($clinicId, 200, $today, $expiringBefore)),
            'futureOrderCount' => count($this->getFutureOrderCandidates($clinicId, 200)),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getStockLotRows(?string $warehouseId, int $limit, string $today, string $expiringBefore): array
    {
        $where = ['deleted' => false];

        if ($warehouseId !== null) {
            $where['warehouseId'] = $warehouseId;
        }

        /** @var iterable<InventoryStockLot> $lots */
        $lots = $this->entityManager
            ->getRDBRepository(InventoryStockLot::ENTITY_TYPE)
            ->where($where)
            ->order('expiresAt', 'ASC')
            ->find();

        $rows = [];

        foreach ($lots as $lot) {
            if ($lot->getQuantityInPurchasingUnits() <= 0.0) {
                continue;
            }

            $rows[] = $this->buildStockLotRow($lot, $today, $expiringBefore);

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getLowStockRows(?string $clinicId, int $limit): array
    {
        $where = [
            'deleted' => false,
            'isActive' => true,
            'stockLevel' => [Material::LEVEL_LOW, Material::LEVEL_CRITICAL, Material::LEVEL_OUT],
        ];

        /** @var iterable<Material> $materials */
        $materials = $this->entityManager
            ->getRDBRepository(Material::ENTITY_TYPE)
            ->where($where)
            ->order('stockLevel', 'DESC')
            ->find();

        $rows = [];

        foreach ($materials as $material) {
            $rows[] = [
                'materialId' => (string) $material->getId(),
                'materialName' => (string) ($material->get('name') ?: $material->getId()),
                'categoryName' => (string) ($material->get('categoryName') ?? ''),
                'stockLevel' => (string) ($material->get('stockLevel') ?: $material->computeLevel()),
                'currentStock' => round($material->getCurrentStock(), 3),
                'minStock' => round($material->getMinStock(), 3),
                'criticalStock' => round($material->getCriticalStock(), 3),
                'unit' => $material->getConsumptionUnit(),
                'reorderUrl' => (string) ($material->get('reorderUrl') ?? ''),
                'openAlertCount' => $this->countOpenAlerts((string) $material->getId(), $clinicId),
            ];

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getExpiringLotRows(?string $clinicId, int $limit, string $today, string $expiringBefore): array
    {
        /** @var iterable<InventoryStockLot> $lots */
        $lots = $this->entityManager
            ->getRDBRepository(InventoryStockLot::ENTITY_TYPE)
            ->where([
                'deleted' => false,
                'expiresAt<=' => $expiringBefore,
            ])
            ->order('expiresAt', 'ASC')
            ->find();

        $rows = [];

        foreach ($lots as $lot) {
            if ($lot->getQuantityInPurchasingUnits() <= 0.0) {
                continue;
            }

            $warehouse = $this->getEntityById(InventoryWarehouse::ENTITY_TYPE, (string) ($lot->get('warehouseId') ?? ''));
            if ($clinicId !== null && $warehouse && (string) ($warehouse->get('clinicId') ?? '') !== $clinicId) {
                continue;
            }

            $rows[] = $this->buildStockLotRow($lot, $today, $expiringBefore);

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getFutureOrderCandidates(?string $clinicId, int $limit): array
    {
        $rows = [];

        foreach ($this->getLowStockRows($clinicId, 200) as $row) {
            $material = $this->getEntityById(Material::ENTITY_TYPE, (string) $row['materialId']);
            if (!$material) {
                continue;
            }

            $maxStock = round((float) ($material->get('maxStock') ?? 0.0), 3);
            $currentStock = round((float) ($row['currentStock'] ?? 0.0), 3);
            $targetStock = $maxStock > 0.0 ? $maxStock : max((float) ($row['minStock'] ?? 0.0), $currentStock);
            $suggestedQuantity = max(0.0, round($targetStock - $currentStock, 3));

            $rows[] = $row + [
                'maxStock' => $maxStock,
                'suggestedQuantity' => $suggestedQuantity,
                'hasReorderUrl' => (string) ($row['reorderUrl'] ?? '') !== '',
            ];

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getCabinetIssueRows(?string $clinicId, int $limit): array
    {
        $satelliteWarehouseIds = $this->getSatelliteWarehouseIds($clinicId);
        if ($satelliteWarehouseIds === []) {
            return [];
        }

        /** @var iterable<StockMovement> $movements */
        $movements = $this->entityManager
            ->getRDBRepository(StockMovement::ENTITY_TYPE)
            ->where([
                'deleted' => false,
                'type' => [StockMovement::TYPE_TRANSFER_OUT, StockMovement::TYPE_TRANSFER_IN],
            ])
            ->order('performedAt', 'DESC')
            ->find();

        $rows = [];

        foreach ($movements as $movement) {
            $sourceWarehouseId = (string) ($movement->get('sourceWarehouseId') ?? '');
            $targetWarehouseId = (string) ($movement->get('targetWarehouseId') ?? '');

            if (
                !in_array($sourceWarehouseId, $satelliteWarehouseIds, true)
                && !in_array($targetWarehouseId, $satelliteWarehouseIds, true)
            ) {
                continue;
            }

            $rows[] = $this->buildMovementRow($movement);

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getRecentMovements(?string $clinicId, ?string $warehouseId, int $limit): array
    {
        $where = ['deleted' => false];

        if ($clinicId !== null) {
            $where['clinicId'] = $clinicId;
        }

        /** @var iterable<StockMovement> $movements */
        $movements = $this->entityManager
            ->getRDBRepository(StockMovement::ENTITY_TYPE)
            ->where($where)
            ->order('performedAt', 'DESC')
            ->find();

        $rows = [];

        foreach ($movements as $movement) {
            if ($warehouseId !== null) {
                $sourceWarehouseId = (string) ($movement->get('sourceWarehouseId') ?? '');
                $targetWarehouseId = (string) ($movement->get('targetWarehouseId') ?? '');

                if ($sourceWarehouseId !== $warehouseId && $targetWarehouseId !== $warehouseId) {
                    continue;
                }
            }

            $rows[] = $this->buildMovementRow($movement);

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStockLotRow(InventoryStockLot $lot, string $today, string $expiringBefore): array
    {
        $expiresAt = (string) ($lot->get('expiresAt') ?? '');

        return [
            'id' => (string) $lot->getId(),
            'warehouseId' => (string) ($lot->get('warehouseId') ?? ''),
            'warehouseName' => (string) ($lot->get('warehouseName') ?? ''),
            'materialId' => (string) ($lot->get('materialId') ?? ''),
            'materialName' => (string) ($lot->get('materialName') ?? ''),
            'quantityInPurchasingUnits' => round($lot->getQuantityInPurchasingUnits(), 3),
            'purchasingUnit' => $this->getMaterialPurchasingUnit((string) ($lot->get('materialId') ?? '')),
            'lotNumber' => (string) ($lot->get('lotNumber') ?? ''),
            'expiresAt' => $expiresAt,
            'receivedAt' => (string) ($lot->get('receivedAt') ?? ''),
            'expiryStatus' => $this->getExpiryStatus($expiresAt, $today, $expiringBefore),
        ];
    }

    private function getExpiryStatus(string $expiresAt, string $today, string $expiringBefore): string
    {
        if ($expiresAt === '') {
            return 'ok';
        }

        if ($expiresAt < $today) {
            return 'expired';
        }

        if ($expiresAt <= $expiringBefore) {
            return 'expiring';
        }

        return 'ok';
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMovementRow(StockMovement $movement): array
    {
        return [
            'id' => (string) $movement->getId(),
            'materialId' => (string) ($movement->get('materialId') ?? ''),
            'materialName' => (string) ($movement->get('materialName') ?? ''),
            'clinicId' => (string) ($movement->get('clinicId') ?? ''),
            'clinicName' => (string) ($movement->get('clinicName') ?? ''),
            'type' => (string) ($movement->get('type') ?? ''),
            'direction' => (string) ($movement->get('direction') ?? ''),
            'quantity' => round($movement->getQuantity(), 3),
            'unit' => (string) ($movement->get('unit') ?? ''),
            'performedAt' => (string) ($movement->get('performedAt') ?? ''),
            'sourceWarehouseId' => (string) ($movement->get('sourceWarehouseId') ?? ''),
            'sourceWarehouseName' => (string) ($movement->get('sourceWarehouseName') ?? ''),
            'targetWarehouseId' => (string) ($movement->get('targetWarehouseId') ?? ''),
            'targetWarehouseName' => (string) ($movement->get('targetWarehouseName') ?? ''),
            'stockLotId' => (string) ($movement->get('stockLotId') ?? ''),
            'stockLotName' => (string) ($movement->get('stockLotName') ?? ''),
            'reason' => (string) ($movement->get('reason') ?? ''),
        ];
    }

    private function countLotsForWarehouse(string $warehouseId): int
    {
        return $this->entityManager
            ->getRDBRepository(InventoryStockLot::ENTITY_TYPE)
            ->where([
                'deleted' => false,
                'warehouseId' => $warehouseId,
            ])
            ->count();
    }

    private function sumWarehouseStockValue(string $warehouseId): float
    {
        /** @var iterable<InventoryStockLot> $lots */
        $lots = $this->entityManager
            ->getRDBRepository(InventoryStockLot::ENTITY_TYPE)
            ->where([
                'deleted' => false,
                'warehouseId' => $warehouseId,
            ])
            ->find();

        $sum = 0.0;
        foreach ($lots as $lot) {
            $material = $this->getEntityById(Material::ENTITY_TYPE, (string) ($lot->get('materialId') ?? ''));
            $price = $material ? (float) ($material->get('price') ?? 0.0) : 0.0;
            $sum += $lot->getQuantityInPurchasingUnits() * $price;
        }

        return round($sum, 2);
    }

    private function countOpenAlerts(string $materialId, ?string $clinicId): int
    {
        $where = [
            'deleted' => false,
            'materialId' => $materialId,
            'status' => LowStockAlert::STATUS_OPEN,
        ];

        if ($clinicId !== null) {
            $where['clinicId'] = $clinicId;
        }

        return $this->entityManager
            ->getRDBRepository(LowStockAlert::ENTITY_TYPE)
            ->where($where)
            ->count();
    }

    /**
     * @return list<string>
     */
    private function getSatelliteWarehouseIds(?string $clinicId): array
    {
        $where = [
            'deleted' => false,
            'isActive' => true,
            'warehouseType' => InventoryWarehouse::TYPE_SATELLITE,
        ];

        if ($clinicId !== null) {
            $where['clinicId'] = $clinicId;
        }

        /** @var iterable<InventoryWarehouse> $warehouses */
        $warehouses = $this->entityManager
            ->getRDBRepository(InventoryWarehouse::ENTITY_TYPE)
            ->where($where)
            ->find();

        $ids = [];
        foreach ($warehouses as $warehouse) {
            $ids[] = (string) $warehouse->getId();
        }

        return $ids;
    }

    private function getMaterialPurchasingUnit(string $materialId): string
    {
        $material = $this->getEntityById(Material::ENTITY_TYPE, $materialId);

        return $material instanceof Material ? $material->getPurchasingUnit() : '';
    }

    private function getEntityById(string $entityType, string $id): ?Entity
    {
        if ($id === '') {
            return null;
        }

        return $this->entityManager->getEntityById($entityType, $id);
    }

    private function normalizeOptionalId(?string $id): ?string
    {
        $id = trim((string) $id);

        return $id === '' ? null : $id;
    }
}
