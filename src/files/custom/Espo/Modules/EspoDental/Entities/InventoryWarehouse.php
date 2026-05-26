<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class InventoryWarehouse extends Entity
{
    public const ENTITY_TYPE = 'InventoryWarehouse';

    public const TYPE_MAIN = 'main';
    public const TYPE_SATELLITE = 'satellite';

    public function isMain(): bool
    {
        return $this->get('warehouseType') === self::TYPE_MAIN;
    }

    public function isSatellite(): bool
    {
        return $this->get('warehouseType') === self::TYPE_SATELLITE;
    }
}
