<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class FinancialAdjustment extends Entity
{
    public const ENTITY_TYPE = 'FinancialAdjustment';

    public const TYPE_WRITE_OFF = 'write_off';
    public const TYPE_COMPLAINT = 'complaint';
    public const TYPE_MANUAL_CHARGE = 'manual_charge';
}
