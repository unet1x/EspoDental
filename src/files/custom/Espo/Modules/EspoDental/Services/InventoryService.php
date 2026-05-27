<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\EntityManager;
use Espo\Entities\User;
use Espo\Modules\EspoDental\Entities\InventoryWarehouse;
use Espo\Modules\EspoDental\Entities\InventoryStockLot;
use Espo\Modules\EspoDental\Entities\LowStockAlert;
use Espo\Modules\EspoDental\Entities\Material;
use Espo\Modules\EspoDental\Entities\StockMovement;
use Espo\ORM\Entity;

class InventoryService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly User $user
    ) {
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
            'actionOptions' => [
                'materials' => $this->getMaterialOptions($limit * 5),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{movementId: string, stockLotId: string, workspace: array<string, mixed>}
     */
    public function receive(array $data): array
    {
        /** @var array{movementId: string, stockLotId: string, workspace: array<string, mixed>} $result */
        $result = $this->entityManager->getTransactionManager()->run(
            fn (): array => $this->receiveInTransaction($data)
        );

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{
     *     sourceMovementId: string,
     *     targetMovementId: string,
     *     sourceStockLotId: string,
     *     targetStockLotId: string,
     *     workspace: array<string, mixed>
     * }
     */
    public function transfer(array $data): array
    {
        /** @var array<string, mixed> $result */
        $result = $this->entityManager->getTransactionManager()->run(
            fn (): array => $this->transferInTransaction($data)
        );

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{movementId: string, stockLotId: string, workspace: array<string, mixed>}
     */
    public function writeOff(array $data): array
    {
        /** @var array{movementId: string, stockLotId: string, workspace: array<string, mixed>} $result */
        $result = $this->entityManager->getTransactionManager()->run(
            fn (): array => $this->writeOffInTransaction($data)
        );

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{movementId: ?string, stockLotId: string, changed: bool, workspace: array<string, mixed>}
     */
    public function adjust(array $data): array
    {
        /** @var array{movementId: ?string, stockLotId: string, changed: bool, workspace: array<string, mixed>} $result */
        $result = $this->entityManager->getTransactionManager()->run(
            fn (): array => $this->adjustInTransaction($data)
        );

        return $result;
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
     * @param array<string, mixed> $data
     * @return array{movementId: string, stockLotId: string, workspace: array<string, mixed>}
     */
    private function receiveInTransaction(array $data): array
    {
        $warehouse = $this->getWarehouseOrFail((string) ($data['warehouseId'] ?? $data['targetWarehouseId'] ?? ''));
        $material = $this->getMaterialOrFail((string) ($data['materialId'] ?? ''));
        $quantity = $this->getPositiveQuantity($data['quantity'] ?? null);
        $lotNumber = trim((string) ($data['lotNumber'] ?? $data['batch'] ?? ''));
        $expiresAt = $this->normalizeOptionalDate($data['expiresAt'] ?? $data['expiryDate'] ?? null);

        $this->assertReceiptExpiration($material, $expiresAt);

        $lot = $this->createStockLot(
            $warehouse,
            $material,
            $quantity,
            $lotNumber,
            $expiresAt,
            $this->normalizeOptionalDate($data['receivedAt'] ?? null) ?? (new DateTimeImmutable())->format('Y-m-d'),
            null
        );

        $movement = $this->createMovement(
            $material,
            $this->resolveClinicId($warehouse, $data['clinicId'] ?? null),
            StockMovement::TYPE_RECEIPT,
            $quantity,
            null,
            $warehouse,
            $lot,
            $this->normalizeReason($data['reason'] ?? null, 'Поступление на склад'),
            $data['unitPrice'] ?? null
        );
        $movement->set('batch', $lotNumber);
        $movement->set('expiryDate', $expiresAt);
        $this->entityManager->saveEntity($movement);

        $lot->set('sourceTransactionId', $movement->getId());
        $this->entityManager->saveEntity($lot);

        return [
            'movementId' => (string) $movement->getId(),
            'stockLotId' => (string) $lot->getId(),
            'workspace' => $this->getWorkspace(null, (string) $warehouse->getId()),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{
     *     sourceMovementId: string,
     *     targetMovementId: string,
     *     sourceStockLotId: string,
     *     targetStockLotId: string,
     *     workspace: array<string, mixed>
     * }
     */
    private function transferInTransaction(array $data): array
    {
        $sourceLot = $this->getStockLotOrFail((string) ($data['stockLotId'] ?? $data['sourceStockLotId'] ?? ''));
        $sourceWarehouse = $this->getWarehouseOrFail((string) ($sourceLot->get('warehouseId') ?? ''));
        $targetWarehouse = $this->getWarehouseOrFail((string) ($data['targetWarehouseId'] ?? ''));

        if ((string) $sourceWarehouse->getId() === (string) $targetWarehouse->getId()) {
            throw new BadRequest('Source and target warehouses must be different');
        }

        $this->assertSameClinicTransfer($sourceWarehouse, $targetWarehouse);

        $material = $this->getMaterialOrFail((string) ($sourceLot->get('materialId') ?? ''));
        $quantity = $this->getPositiveQuantity($data['quantity'] ?? null);
        $reason = $this->normalizeReason($data['reason'] ?? null, 'Перемещение между складами');

        $this->decreaseLotQuantity($sourceLot, $quantity);

        $sourceMovement = $this->createMovement(
            $material,
            $this->resolveClinicId($sourceWarehouse, $data['clinicId'] ?? null),
            StockMovement::TYPE_TRANSFER_OUT,
            $quantity,
            $sourceWarehouse,
            $targetWarehouse,
            $sourceLot,
            $reason,
            $data['unitPrice'] ?? null
        );
        $this->entityManager->saveEntity($sourceMovement);

        $targetLot = $this->findOrCreateMatchingLot($targetWarehouse, $material, $sourceLot);
        $this->increaseLotQuantity($targetLot, $quantity);

        $targetMovement = $this->createMovement(
            $material,
            $this->resolveClinicId($targetWarehouse, $data['clinicId'] ?? null),
            StockMovement::TYPE_TRANSFER_IN,
            $quantity,
            $sourceWarehouse,
            $targetWarehouse,
            $targetLot,
            $reason,
            $data['unitPrice'] ?? null
        );
        $this->entityManager->saveEntity($targetMovement);

        if (!$targetLot->get('sourceTransactionId')) {
            $targetLot->set('sourceTransactionId', $targetMovement->getId());
            $this->entityManager->saveEntity($targetLot);
        }

        return [
            'sourceMovementId' => (string) $sourceMovement->getId(),
            'targetMovementId' => (string) $targetMovement->getId(),
            'sourceStockLotId' => (string) $sourceLot->getId(),
            'targetStockLotId' => (string) $targetLot->getId(),
            'workspace' => $this->getWorkspace(null, (string) $sourceWarehouse->getId()),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{movementId: string, stockLotId: string, workspace: array<string, mixed>}
     */
    private function writeOffInTransaction(array $data): array
    {
        $lot = $this->getStockLotOrFail((string) ($data['stockLotId'] ?? ''));
        $warehouse = $this->getWarehouseOrFail((string) ($lot->get('warehouseId') ?? ''));
        $material = $this->getMaterialOrFail((string) ($lot->get('materialId') ?? ''));
        $quantity = $this->getPositiveQuantity($data['quantity'] ?? null);
        $reason = $this->requireReason($data['reason'] ?? null);

        $this->decreaseLotQuantity($lot, $quantity);

        $movement = $this->createMovement(
            $material,
            $this->resolveClinicId($warehouse, $data['clinicId'] ?? null),
            StockMovement::TYPE_WRITEOFF,
            $quantity,
            $warehouse,
            null,
            $lot,
            $reason,
            $data['unitPrice'] ?? null
        );
        $this->entityManager->saveEntity($movement);

        return [
            'movementId' => (string) $movement->getId(),
            'stockLotId' => (string) $lot->getId(),
            'workspace' => $this->getWorkspace(null, (string) $warehouse->getId()),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{movementId: ?string, stockLotId: string, changed: bool, workspace: array<string, mixed>}
     */
    private function adjustInTransaction(array $data): array
    {
        $mode = (string) ($data['adjustmentType'] ?? $data['mode'] ?? 'set');
        $reason = $this->requireReason($data['reason'] ?? null);

        if (in_array($mode, ['increase', StockMovement::TYPE_MANUAL_INCREASE], true)) {
            return $this->increaseInTransaction($data, $reason);
        }

        if (in_array($mode, ['decrease', StockMovement::TYPE_MANUAL_DECREASE], true)) {
            return $this->decreaseInTransaction($data, $reason);
        }

        return $this->setLotQuantityInTransaction($data, $reason);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{movementId: string, stockLotId: string, changed: bool, workspace: array<string, mixed>}
     */
    private function increaseInTransaction(array $data, string $reason): array
    {
        $warehouse = $this->getWarehouseOrFail((string) ($data['warehouseId'] ?? $data['targetWarehouseId'] ?? ''));
        $material = $this->getMaterialOrFail((string) ($data['materialId'] ?? ''));
        $quantity = $this->getPositiveQuantity($data['quantity'] ?? null);
        $expiresAt = $this->normalizeOptionalDate($data['expiresAt'] ?? $data['expiryDate'] ?? null);

        $this->assertReceiptExpiration($material, $expiresAt);

        $lot = $this->findOrCreateStockLot(
            $warehouse,
            $material,
            trim((string) ($data['lotNumber'] ?? $data['batch'] ?? '')),
            $expiresAt,
            $this->normalizeOptionalDate($data['receivedAt'] ?? null) ?? (new DateTimeImmutable())->format('Y-m-d')
        );
        $this->increaseLotQuantity($lot, $quantity);

        $movement = $this->createMovement(
            $material,
            $this->resolveClinicId($warehouse, $data['clinicId'] ?? null),
            StockMovement::TYPE_MANUAL_INCREASE,
            $quantity,
            null,
            $warehouse,
            $lot,
            $reason,
            $data['unitPrice'] ?? null
        );
        $movement->set('batch', (string) ($lot->get('lotNumber') ?? ''));
        $movement->set('expiryDate', $this->normalizeOptionalDate($lot->get('expiresAt')));
        $this->entityManager->saveEntity($movement);

        if (!$lot->get('sourceTransactionId')) {
            $lot->set('sourceTransactionId', $movement->getId());
            $this->entityManager->saveEntity($lot);
        }

        return [
            'movementId' => (string) $movement->getId(),
            'stockLotId' => (string) $lot->getId(),
            'changed' => true,
            'workspace' => $this->getWorkspace(null, (string) $warehouse->getId()),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{movementId: string, stockLotId: string, changed: bool, workspace: array<string, mixed>}
     */
    private function decreaseInTransaction(array $data, string $reason): array
    {
        $lot = $this->getStockLotOrFail((string) ($data['stockLotId'] ?? ''));
        $warehouse = $this->getWarehouseOrFail((string) ($lot->get('warehouseId') ?? ''));
        $material = $this->getMaterialOrFail((string) ($lot->get('materialId') ?? ''));
        $quantity = $this->getPositiveQuantity($data['quantity'] ?? null);

        $this->decreaseLotQuantity($lot, $quantity);

        $movement = $this->createMovement(
            $material,
            $this->resolveClinicId($warehouse, $data['clinicId'] ?? null),
            StockMovement::TYPE_MANUAL_DECREASE,
            $quantity,
            $warehouse,
            null,
            $lot,
            $reason,
            $data['unitPrice'] ?? null
        );
        $this->entityManager->saveEntity($movement);

        return [
            'movementId' => (string) $movement->getId(),
            'stockLotId' => (string) $lot->getId(),
            'changed' => true,
            'workspace' => $this->getWorkspace(null, (string) $warehouse->getId()),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{movementId: ?string, stockLotId: string, changed: bool, workspace: array<string, mixed>}
     */
    private function setLotQuantityInTransaction(array $data, string $reason): array
    {
        $lot = $this->getStockLotOrFail((string) ($data['stockLotId'] ?? ''));
        $warehouse = $this->getWarehouseOrFail((string) ($lot->get('warehouseId') ?? ''));
        $material = $this->getMaterialOrFail((string) ($lot->get('materialId') ?? ''));
        $countedQuantity = $this->getNonNegativeQuantity($data['countedQuantity'] ?? $data['quantity'] ?? null);
        $currentQuantity = round($lot->getQuantityInPurchasingUnits(), 4);
        $delta = round($countedQuantity - $currentQuantity, 4);

        if ($delta === 0.0) {
            return [
                'movementId' => null,
                'stockLotId' => (string) $lot->getId(),
                'changed' => false,
                'workspace' => $this->getWorkspace(null, (string) $warehouse->getId()),
            ];
        }

        $movementType = $delta > 0 ? StockMovement::TYPE_MANUAL_INCREASE : StockMovement::TYPE_MANUAL_DECREASE;
        $movementQuantity = abs($delta);
        $lot->set('quantityInPurchasingUnits', $countedQuantity);
        $this->entityManager->saveEntity($lot);

        $movement = $this->createMovement(
            $material,
            $this->resolveClinicId($warehouse, $data['clinicId'] ?? null),
            $movementType,
            $movementQuantity,
            $delta < 0 ? $warehouse : null,
            $delta > 0 ? $warehouse : null,
            $lot,
            $reason,
            $data['unitPrice'] ?? null
        );
        $this->entityManager->saveEntity($movement);

        return [
            'movementId' => (string) $movement->getId(),
            'stockLotId' => (string) $lot->getId(),
            'changed' => true,
            'workspace' => $this->getWorkspace(null, (string) $warehouse->getId()),
        ];
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
    private function getWorkspaceSummary(
        array $warehouses,
        ?string $clinicId,
        string $today,
        string $expiringBefore
    ): array {
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

            $warehouse = $this->getEntityById(
                InventoryWarehouse::ENTITY_TYPE,
                (string) ($lot->get('warehouseId') ?? '')
            );
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

    /**
     * @return list<array<string, mixed>>
     */
    private function getMaterialOptions(int $limit): array
    {
        /** @var iterable<Material> $materials */
        $materials = $this->entityManager
            ->getRDBRepository(Material::ENTITY_TYPE)
            ->where([
                'deleted' => false,
                'isActive' => true,
            ])
            ->order('name', 'ASC')
            ->find();

        $rows = [];

        foreach ($materials as $material) {
            $rows[] = [
                'id' => (string) $material->getId(),
                'name' => (string) ($material->get('name') ?: $material->getId()),
                'code' => (string) ($material->get('code') ?? ''),
                'consumptionUnit' => $material->getConsumptionUnit(),
                'purchasingUnit' => $material->getPurchasingUnit(),
                'conversionFactor' => $material->getConversionFactor(),
                'trackExpiration' => $material->tracksExpiration(),
                'price' => (float) ($material->get('price') ?? 0.0),
                'priceCurrency' => (string) ($material->get('priceCurrency') ?? ''),
            ];

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    private function createMovement(
        Material $material,
        string $clinicId,
        string $type,
        float $quantityInPurchasingUnits,
        ?InventoryWarehouse $sourceWarehouse,
        ?InventoryWarehouse $targetWarehouse,
        ?InventoryStockLot $stockLot,
        string $reason,
        mixed $unitPrice
    ): StockMovement {
        if ($clinicId === '') {
            throw new BadRequest('clinicId is required');
        }

        /** @var StockMovement $movement */
        $movement = $this->entityManager->getNewEntity(StockMovement::ENTITY_TYPE);
        $movement->set('materialId', $material->getId());
        $movement->set('clinicId', $clinicId);
        $movement->set('type', $type);
        $movement->set('quantity', $this->toConsumptionQuantity($material, $quantityInPurchasingUnits));
        $movement->set('unit', $material->getConsumptionUnit());
        $movement->set('unitPrice', $this->normalizeUnitPrice($unitPrice, $material));
        $movement->set('performedAt', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
        $movement->set('performedById', $this->user->getId());
        $movement->set('reason', $reason);

        $currency = (string) ($material->get('priceCurrency') ?? '');
        if ($currency !== '') {
            $movement->set('unitPriceCurrency', $currency);
        }
        if ($sourceWarehouse) {
            $movement->set('sourceWarehouseId', $sourceWarehouse->getId());
        }
        if ($targetWarehouse) {
            $movement->set('targetWarehouseId', $targetWarehouse->getId());
        }
        if ($stockLot) {
            $movement->set('stockLotId', $stockLot->getId());
        }

        return $movement;
    }

    private function createStockLot(
        InventoryWarehouse $warehouse,
        Material $material,
        float $quantity,
        string $lotNumber,
        ?string $expiresAt,
        string $receivedAt,
        ?string $sourceTransactionId
    ): InventoryStockLot {
        /** @var InventoryStockLot $lot */
        $lot = $this->entityManager->getNewEntity(InventoryStockLot::ENTITY_TYPE);
        $lot->set('warehouseId', $warehouse->getId());
        $lot->set('materialId', $material->getId());
        $lot->set('quantityInPurchasingUnits', round($quantity, 4));
        $lot->set('lotNumber', $lotNumber);
        $lot->set('expiresAt', $expiresAt);
        $lot->set('receivedAt', $receivedAt);
        $lot->set('sourceTransactionId', $sourceTransactionId);
        $this->entityManager->saveEntity($lot);

        return $lot;
    }

    private function findOrCreateMatchingLot(
        InventoryWarehouse $warehouse,
        Material $material,
        InventoryStockLot $sourceLot
    ): InventoryStockLot {
        return $this->findOrCreateStockLot(
            $warehouse,
            $material,
            (string) ($sourceLot->get('lotNumber') ?? ''),
            $this->normalizeOptionalDate($sourceLot->get('expiresAt')),
            $this->normalizeOptionalDate($sourceLot->get('receivedAt')) ?? (new DateTimeImmutable())->format('Y-m-d')
        );
    }

    private function findOrCreateStockLot(
        InventoryWarehouse $warehouse,
        Material $material,
        string $lotNumber,
        ?string $expiresAt,
        string $receivedAt
    ): InventoryStockLot {
        /** @var InventoryStockLot|null $existing */
        $existing = $this->entityManager
            ->getRDBRepository(InventoryStockLot::ENTITY_TYPE)
            ->where([
                'deleted' => false,
                'warehouseId' => $warehouse->getId(),
                'materialId' => $material->getId(),
                'lotNumber' => $lotNumber,
                'expiresAt' => $expiresAt,
            ])
            ->findOne();

        if ($existing instanceof InventoryStockLot) {
            return $existing;
        }

        return $this->createStockLot($warehouse, $material, 0.0, $lotNumber, $expiresAt, $receivedAt, null);
    }

    private function decreaseLotQuantity(InventoryStockLot $lot, float $quantity): void
    {
        $current = round($lot->getQuantityInPurchasingUnits(), 4);
        if ($quantity > $current) {
            throw new BadRequest('Quantity exceeds available lot balance');
        }

        $lot->set('quantityInPurchasingUnits', round($current - $quantity, 4));
        $this->entityManager->saveEntity($lot);
    }

    private function increaseLotQuantity(InventoryStockLot $lot, float $quantity): void
    {
        $lot->set('quantityInPurchasingUnits', round($lot->getQuantityInPurchasingUnits() + $quantity, 4));
        $this->entityManager->saveEntity($lot);
    }

    private function getWarehouseOrFail(string $id): InventoryWarehouse
    {
        /** @var InventoryWarehouse|null $warehouse */
        $warehouse = $this->getEntityById(InventoryWarehouse::ENTITY_TYPE, $id);
        if (!$warehouse instanceof InventoryWarehouse) {
            throw new NotFound('Warehouse not found');
        }

        return $warehouse;
    }

    private function getStockLotOrFail(string $id): InventoryStockLot
    {
        /** @var InventoryStockLot|null $lot */
        $lot = $this->getEntityById(InventoryStockLot::ENTITY_TYPE, $id);
        if (!$lot instanceof InventoryStockLot) {
            throw new NotFound('Stock lot not found');
        }

        return $lot;
    }

    private function getMaterialOrFail(string $id): Material
    {
        /** @var Material|null $material */
        $material = $this->getEntityById(Material::ENTITY_TYPE, $id);
        if (!$material instanceof Material) {
            throw new NotFound('Material not found');
        }

        return $material;
    }

    private function assertSameClinicTransfer(
        InventoryWarehouse $sourceWarehouse,
        InventoryWarehouse $targetWarehouse
    ): void {
        $sourceClinicId = (string) ($sourceWarehouse->get('clinicId') ?? '');
        $targetClinicId = (string) ($targetWarehouse->get('clinicId') ?? '');

        if ($sourceClinicId !== '' && $targetClinicId !== '' && $sourceClinicId !== $targetClinicId) {
            throw new BadRequest('Transfer between clinics is not supported');
        }
    }

    private function resolveClinicId(InventoryWarehouse $warehouse, mixed $clinicId): string
    {
        $clinicId = trim((string) ($clinicId ?? ''));
        if ($clinicId !== '') {
            return $clinicId;
        }

        return (string) ($warehouse->get('clinicId') ?? '');
    }

    private function getPositiveQuantity(mixed $value): float
    {
        $quantity = $this->getNonNegativeQuantity($value);
        if ($quantity <= 0.0) {
            throw new BadRequest('Quantity must be positive');
        }

        return $quantity;
    }

    private function getNonNegativeQuantity(mixed $value): float
    {
        if (!is_numeric($value)) {
            throw new BadRequest('Quantity must be numeric');
        }

        $quantity = round((float) $value, 4);
        if ($quantity < 0.0) {
            throw new BadRequest('Quantity cannot be negative');
        }

        return $quantity;
    }

    private function normalizeUnitPrice(mixed $unitPrice, Material $material): float
    {
        if (is_numeric($unitPrice)) {
            return round((float) $unitPrice, 4);
        }

        return round((float) ($material->get('price') ?? 0.0), 4);
    }

    private function toConsumptionQuantity(Material $material, float $quantityInPurchasingUnits): float
    {
        return round($quantityInPurchasingUnits * $material->getConversionFactor(), 4);
    }

    private function normalizeReason(mixed $reason, string $fallback): string
    {
        $reason = trim((string) ($reason ?? ''));

        return $reason !== '' ? $reason : $fallback;
    }

    private function requireReason(mixed $reason): string
    {
        $reason = trim((string) ($reason ?? ''));
        if ($reason === '') {
            throw new BadRequest('Reason is required');
        }

        return $reason;
    }

    private function normalizeOptionalDate(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
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
