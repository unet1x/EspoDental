<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class Service extends Entity
{
    public const ENTITY_TYPE = 'Service';

    public function getPrice(): float
    {
        return (float) $this->get('price');
    }

    public function getVatRate(): float
    {
        return (float) $this->get('vatRate');
    }

    public function isActive(): bool
    {
        return (bool) $this->get('isActive');
    }

    public function isPerTooth(): bool
    {
        return (bool) $this->get('perTooth');
    }
}
