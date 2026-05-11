<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class Material extends Entity
{
    public const ENTITY_TYPE = 'Material';

    public const LEVEL_OK = 'ok';
    public const LEVEL_LOW = 'low';
    public const LEVEL_CRITICAL = 'critical';
    public const LEVEL_OUT = 'out';

    public function getCurrentStock(): float
    {
        return (float) $this->get('currentStock');
    }

    public function getMinStock(): float
    {
        return (float) $this->get('minStock');
    }

    public function getCriticalStock(): float
    {
        return (float) $this->get('criticalStock');
    }

    public function getMaxStock(): float
    {
        return (float) $this->get('maxStock');
    }

    public function getUnit(): ?string
    {
        return $this->get('unit');
    }

    public function isActive(): bool
    {
        return (bool) $this->get('isActive');
    }

    public function computeLevel(): string
    {
        $current = $this->getCurrentStock();
        $critical = $this->getCriticalStock();
        $min = $this->getMinStock();

        if ($current <= 0) {
            return self::LEVEL_OUT;
        }
        if ($critical > 0 && $current <= $critical) {
            return self::LEVEL_CRITICAL;
        }
        if ($min > 0 && $current <= $min) {
            return self::LEVEL_LOW;
        }
        return self::LEVEL_OK;
    }
}
