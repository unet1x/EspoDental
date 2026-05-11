<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Classes\Select\Appointment\BoolFilters;

use DateTimeImmutable;
use Espo\Core\Select\Bool\Filter;
use Espo\Entities\User;
use Espo\Modules\EspoDental\Entities\Appointment;
use Espo\ORM\Query\Part\WhereClause;
use Espo\ORM\Query\Part\WhereItem;

class Upcoming implements Filter
{
    public function apply(User $user): ?WhereItem
    {
        return WhereClause::fromRaw([
            'dateStart>=' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            'status' => Appointment::BLOCKING_STATUSES,
        ]);
    }
}
