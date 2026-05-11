<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Classes\Select\SalaryProfile\BoolFilters;

use Espo\Core\Select\Bool\Filter;
use Espo\Entities\User;
use Espo\ORM\Query\Part\WhereClause;
use Espo\ORM\Query\Part\WhereItem;

class OnlyActive implements Filter
{
    public function apply(User $user): ?WhereItem
    {
        return WhereClause::fromRaw(['isActive' => true]);
    }
}
