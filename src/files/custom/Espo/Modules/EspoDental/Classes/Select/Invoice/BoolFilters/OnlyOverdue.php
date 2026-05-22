<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Classes\Select\Invoice\BoolFilters;

use DateTimeImmutable;
use Espo\Modules\EspoDental\Classes\Select\Common\RawBoolFilter;
use Espo\Modules\EspoDental\Entities\Invoice;
use Espo\ORM\Query\Part\WhereClause;
use Espo\ORM\Query\Part\WhereItem;

class OnlyOverdue extends RawBoolFilter
{
    protected function buildWhereItem(): WhereItem
    {
        return WhereClause::fromRaw([
            'status' => [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIAL_PAID],
            'dueDate<' => (new DateTimeImmutable('today'))->format('Y-m-d'),
        ]);
    }
}
