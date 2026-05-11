<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Classes\Select\SalaryEntry\BoolFilters;

use Espo\Core\Select\Bool\Filter;
use Espo\Entities\User;
use Espo\Modules\EspoDental\Entities\SalaryEntry;
use Espo\ORM\Query\Part\WhereClause;
use Espo\ORM\Query\Part\WhereItem;

class DraftOnly implements Filter
{
    public function apply(User $user): ?WhereItem
    {
        return WhereClause::fromRaw(['status' => SalaryEntry::STATUS_DRAFT]);
    }
}
