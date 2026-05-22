<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Classes\Select\SalaryEntry\BoolFilters;

use Espo\Modules\EspoDental\Classes\Select\Common\RawBoolFilter;
use Espo\Modules\EspoDental\Classes\Select\Common\DateRangeHelper;
use Espo\ORM\Query\Part\WhereItem;

class ThisMonth extends RawBoolFilter
{
    protected function buildWhereItem(): WhereItem
    {
        return DateRangeHelper::thisMonth('periodFrom');
    }
}
