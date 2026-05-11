<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Classes\Select\SalaryBonus\BoolFilters;

use Espo\Core\Select\Bool\Filter;
use Espo\Entities\User;
use Espo\Modules\EspoDental\Entities\SalaryBonus;
use Espo\ORM\Query\Part\WhereClause;
use Espo\ORM\Query\Part\WhereItem;

class PendingOnly implements Filter
{
    public function apply(User $user): ?WhereItem
    {
        return WhereClause::fromRaw(['status' => SalaryBonus::STATUS_PENDING]);
    }
}
