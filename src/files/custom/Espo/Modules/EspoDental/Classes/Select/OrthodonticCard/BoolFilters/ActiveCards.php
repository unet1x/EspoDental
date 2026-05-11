<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Classes\Select\OrthodonticCard\BoolFilters;

use Espo\Core\Select\Bool\Filter;
use Espo\Entities\User;
use Espo\Modules\EspoDental\Entities\OrthodonticCard;
use Espo\ORM\Query\Part\WhereClause;
use Espo\ORM\Query\Part\WhereItem;

class ActiveCards implements Filter
{
    public function apply(User $user): ?WhereItem
    {
        return WhereClause::fromRaw(['status' => OrthodonticCard::ACTIVE_STATUSES]);
    }
}
