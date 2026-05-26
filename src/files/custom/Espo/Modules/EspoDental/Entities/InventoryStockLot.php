<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class InventoryStockLot extends Entity
{
    public const ENTITY_TYPE = 'InventoryStockLot';

    public function getQuantityInPurchasingUnits(): float
    {
        return (float) $this->get('quantityInPurchasingUnits');
    }

    public function isExpired(string $today): bool
    {
        $expiresAt = (string) ($this->get('expiresAt') ?? '');

        return $expiresAt !== '' && $expiresAt < $today;
    }
}
