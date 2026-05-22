<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Classes\Select\SalaryEntry\BoolFilters;

use Espo\Modules\EspoDental\Classes\Select\Common\RawBoolFilter;
use Espo\Modules\EspoDental\Entities\SalaryEntry;
use Espo\ORM\Query\Part\WhereClause;
use Espo\ORM\Query\Part\WhereItem;

class DraftOnly extends RawBoolFilter
{
    protected function buildWhereItem(): WhereItem
    {
        return WhereClause::fromRaw(['status' => SalaryEntry::STATUS_DRAFT]);
    }
}
