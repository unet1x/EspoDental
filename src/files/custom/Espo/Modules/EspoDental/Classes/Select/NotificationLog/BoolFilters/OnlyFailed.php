<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Classes\Select\NotificationLog\BoolFilters;

use Espo\Core\Select\Bool\Filter;
use Espo\Entities\User;
use Espo\Modules\EspoDental\Entities\NotificationLog;
use Espo\ORM\Query\Part\WhereClause;
use Espo\ORM\Query\Part\WhereItem;

class OnlyFailed implements Filter
{
    public function apply(User $user): ?WhereItem
    {
        return WhereClause::fromRaw(['status' => NotificationLog::STATUS_FAILED]);
    }
}
