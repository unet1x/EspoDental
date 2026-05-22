<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Classes\Select\Appointment\BoolFilters;

use DateTimeImmutable;
use Espo\Modules\EspoDental\Classes\Select\Common\RawBoolFilter;
use Espo\Modules\EspoDental\Entities\Appointment;
use Espo\ORM\Query\Part\WhereClause;
use Espo\ORM\Query\Part\WhereItem;

class Upcoming extends RawBoolFilter
{
    protected function buildWhereItem(): WhereItem
    {
        return WhereClause::fromRaw([
            'dateStart>=' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            'status' => Appointment::BLOCKING_STATUSES,
        ]);
    }
}
