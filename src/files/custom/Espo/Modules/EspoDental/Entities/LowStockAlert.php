<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class LowStockAlert extends Entity
{
    public const ENTITY_TYPE = 'LowStockAlert';

    public const STATUS_OPEN = 'open';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_RESOLVED = 'resolved';

    public const LEVEL_LOW = 'low';
    public const LEVEL_CRITICAL = 'critical';
    public const LEVEL_OUT = 'out';
}
