<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Classes\Select\NotificationLog\BoolFilters;

use Espo\Modules\EspoDental\Classes\Select\Common\RawBoolFilter;
use Espo\Modules\EspoDental\Entities\NotificationLog;
use Espo\ORM\Query\Part\WhereClause;
use Espo\ORM\Query\Part\WhereItem;

class OnlyFailed extends RawBoolFilter
{
    protected function buildWhereItem(): WhereItem
    {
        return WhereClause::fromRaw(['status' => NotificationLog::STATUS_FAILED]);
    }
}
