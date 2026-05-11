<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Classes\Select\Payment\BoolFilters;

use Espo\Core\Select\Bool\Filter;
use Espo\Entities\User;
use Espo\Modules\EspoDental\Classes\Select\Common\DateRangeHelper;
use Espo\ORM\Query\Part\WhereItem;

class ThisMonth implements Filter
{
    public function apply(User $user): ?WhereItem
    {
        return DateRangeHelper::thisMonth('paidAt');
    }
}
