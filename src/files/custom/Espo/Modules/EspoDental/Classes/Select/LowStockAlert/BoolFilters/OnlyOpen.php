<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Classes\Select\LowStockAlert\BoolFilters;

use Espo\Core\Select\Bool\Filter;
use Espo\Entities\User;
use Espo\Modules\EspoDental\Entities\LowStockAlert;
use Espo\ORM\Query\Part\WhereClause;
use Espo\ORM\Query\Part\WhereItem;

class OnlyOpen implements Filter
{
    public function apply(User $user): ?WhereItem
    {
        return WhereClause::fromRaw(['status' => LowStockAlert::STATUS_OPEN]);
    }
}
