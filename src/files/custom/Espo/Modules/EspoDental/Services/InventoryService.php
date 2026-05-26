<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\InventoryStockLot;
use Espo\Modules\EspoDental\Entities\Material;

class InventoryService
{
    public function __construct(private readonly EntityManager $entityManager)
    {
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
}
