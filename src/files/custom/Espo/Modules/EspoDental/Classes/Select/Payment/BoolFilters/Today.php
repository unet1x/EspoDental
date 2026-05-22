<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Classes\Select\Payment\BoolFilters;

use Espo\Modules\EspoDental\Classes\Select\Common\RawBoolFilter;
use Espo\Modules\EspoDental\Classes\Select\Common\DateRangeHelper;
use Espo\ORM\Query\Part\WhereItem;

class Today extends RawBoolFilter
{
    protected function buildWhereItem(): WhereItem
    {
        return DateRangeHelper::today('paidAt');
    }
}
