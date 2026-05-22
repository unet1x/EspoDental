<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Classes\Select\Common;

use Espo\Core\Select\Bool\Filter;
use Espo\ORM\Query\Part\Where\OrGroupBuilder;
use Espo\ORM\Query\Part\WhereItem;
use Espo\ORM\Query\SelectBuilder as QueryBuilder;

abstract class RawBoolFilter implements Filter
{
    public function apply(QueryBuilder $queryBuilder, OrGroupBuilder $orGroupBuilder): void
    {
        $orGroupBuilder->add($this->buildWhereItem());
    }

    abstract protected function buildWhereItem(): WhereItem;
}
