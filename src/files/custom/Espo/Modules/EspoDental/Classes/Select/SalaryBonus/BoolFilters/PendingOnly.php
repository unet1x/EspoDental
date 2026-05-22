<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Classes\Select\SalaryBonus\BoolFilters;

use Espo\Modules\EspoDental\Classes\Select\Common\RawBoolFilter;
use Espo\Modules\EspoDental\Entities\SalaryBonus;
use Espo\ORM\Query\Part\WhereClause;
use Espo\ORM\Query\Part\WhereItem;

class PendingOnly extends RawBoolFilter
{
    protected function buildWhereItem(): WhereItem
    {
        return WhereClause::fromRaw(['status' => SalaryBonus::STATUS_PENDING]);
    }
}
