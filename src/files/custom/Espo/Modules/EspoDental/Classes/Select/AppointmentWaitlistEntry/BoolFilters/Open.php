<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Classes\Select\AppointmentWaitlistEntry\BoolFilters;

use Espo\Modules\EspoDental\Classes\Select\Common\RawBoolFilter;
use Espo\Modules\EspoDental\Entities\AppointmentWaitlistEntry;
use Espo\ORM\Query\Part\WhereClause;
use Espo\ORM\Query\Part\WhereItem;

class Open extends RawBoolFilter
{
    protected function buildWhereItem(): WhereItem
    {
        return WhereClause::fromRaw([
            'status' => [
                AppointmentWaitlistEntry::STATUS_WAITING,
                AppointmentWaitlistEntry::STATUS_OFFERED,
            ],
        ]);
    }
}
