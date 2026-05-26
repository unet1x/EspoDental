<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class StockMovement extends Entity
{
    public const ENTITY_TYPE = 'StockMovement';

    public const TYPE_RECEIPT = 'receipt';
    public const TYPE_CONSUMPTION = 'consumption';
    public const TYPE_WRITEOFF = 'writeoff';
    public const TYPE_TRANSFER_OUT = 'transfer_out';
    public const TYPE_TRANSFER_IN = 'transfer_in';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_RETURN = 'return';
    public const TYPE_MANUAL_INCREASE = 'manual_increase';
    public const TYPE_MANUAL_DECREASE = 'manual_decrease';
    public const TYPE_MANUAL_SET = 'manual_set';
    public const TYPE_INVENTORY_COUNT = 'inventory_count';
    public const TYPE_RECEPTION_USAGE = 'reception_usage';

    public const DIRECTION_IN = 'in';
    public const DIRECTION_OUT = 'out';

    public const INBOUND_TYPES = [
        self::TYPE_RECEIPT,
        self::TYPE_TRANSFER_IN,
        self::TYPE_RETURN,
        self::TYPE_MANUAL_INCREASE,
        self::TYPE_MANUAL_SET,
        self::TYPE_INVENTORY_COUNT,
    ];

    public const MANUAL_CORRECTION_TYPES = [
        self::TYPE_ADJUSTMENT,
        self::TYPE_MANUAL_INCREASE,
        self::TYPE_MANUAL_DECREASE,
        self::TYPE_MANUAL_SET,
        self::TYPE_INVENTORY_COUNT,
        self::TYPE_WRITEOFF,
    ];

    public function getQuantity(): float
    {
        return (float) $this->get('quantity');
    }

    public function getMaterialId(): ?string
    {
        return $this->get('materialId');
    }

    public function getType(): ?string
    {
        return $this->get('type');
    }

    public function getDirection(): ?string
    {
        return $this->get('direction');
    }

    public function getSignedQuantity(): float
    {
        $direction = $this->getDirection();
        if (!$direction && $this->getType()) {
            $direction = in_array($this->getType(), self::INBOUND_TYPES, true)
                ? self::DIRECTION_IN : self::DIRECTION_OUT;
        }
        return $direction === self::DIRECTION_OUT ? -$this->getQuantity() : $this->getQuantity();
    }

    public static function deriveDirection(string $type): string
    {
        if ($type === self::TYPE_ADJUSTMENT) {
            return self::DIRECTION_IN;
        }
        return in_array($type, self::INBOUND_TYPES, true)
            ? self::DIRECTION_IN
            : self::DIRECTION_OUT;
    }
}
