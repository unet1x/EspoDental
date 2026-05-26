<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class CashShift extends Entity
{
    public const ENTITY_TYPE = 'CashShift';

    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';
}
