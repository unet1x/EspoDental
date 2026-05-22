<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Classes\Select\OrthodonticCard\BoolFilters;

use Espo\Modules\EspoDental\Classes\Select\Common\RawBoolFilter;
use Espo\Modules\EspoDental\Entities\OrthodonticCard;
use Espo\ORM\Query\Part\WhereClause;
use Espo\ORM\Query\Part\WhereItem;

class ActiveCards extends RawBoolFilter
{
    protected function buildWhereItem(): WhereItem
    {
        return WhereClause::fromRaw(['status' => OrthodonticCard::ACTIVE_STATUSES]);
    }
}
