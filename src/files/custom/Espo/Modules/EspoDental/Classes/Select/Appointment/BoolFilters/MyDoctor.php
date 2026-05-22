<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Classes\Select\Appointment\BoolFilters;

use Espo\Modules\EspoDental\Classes\Select\Common\UserAwareRawBoolFilter;
use Espo\ORM\Query\Part\WhereClause;
use Espo\ORM\Query\Part\WhereItem;

class MyDoctor extends UserAwareRawBoolFilter
{
    protected function buildWhereItem(): WhereItem
    {
        return WhereClause::fromRaw([
            'doctorId' => $this->user->getId(),
        ]);
    }
}
