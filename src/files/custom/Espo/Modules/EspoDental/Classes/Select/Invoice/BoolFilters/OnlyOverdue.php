<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Classes\Select\Invoice\BoolFilters;

use DateTimeImmutable;
use Espo\Core\Select\Bool\Filter;
use Espo\Entities\User;
use Espo\Modules\EspoDental\Entities\Invoice;
use Espo\ORM\Query\Part\WhereClause;
use Espo\ORM\Query\Part\WhereItem;

class OnlyOverdue implements Filter
{
    public function apply(User $user): ?WhereItem
    {
        return WhereClause::fromRaw([
            'status' => [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIAL_PAID],
            'dueDate<' => (new DateTimeImmutable('today'))->format('Y-m-d'),
        ]);
    }
}
