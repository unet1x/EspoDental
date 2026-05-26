<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class AppointmentWaitlistEntry extends Entity
{
    public const ENTITY_TYPE = 'AppointmentWaitlistEntry';

    public const STATUS_WAITING = 'waiting';
    public const STATUS_OFFERED = 'offered';
    public const STATUS_BOOKED = 'booked';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';
}
