<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class ServiceMaterial extends Entity
{
    public const ENTITY_TYPE = 'ServiceMaterial';

    public function getQuantity(): float
    {
        return (float) $this->get('quantity');
    }
}
