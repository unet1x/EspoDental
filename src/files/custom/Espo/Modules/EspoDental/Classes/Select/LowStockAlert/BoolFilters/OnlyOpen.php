<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Classes\Select\LowStockAlert\BoolFilters;

use Espo\Modules\EspoDental\Classes\Select\Common\RawBoolFilter;
use Espo\Modules\EspoDental\Entities\LowStockAlert;
use Espo\ORM\Query\Part\WhereClause;
use Espo\ORM\Query\Part\WhereItem;

class OnlyOpen extends RawBoolFilter
{
    protected function buildWhereItem(): WhereItem
    {
        return WhereClause::fromRaw(['status' => LowStockAlert::STATUS_OPEN]);
    }
}
