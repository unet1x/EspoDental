<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Classes\Select\Material\BoolFilters;

use Espo\Modules\EspoDental\Classes\Select\Common\RawBoolFilter;
use Espo\Modules\EspoDental\Entities\Material;
use Espo\ORM\Query\Part\WhereClause;
use Espo\ORM\Query\Part\WhereItem;

class CriticalStock extends RawBoolFilter
{
    protected function buildWhereItem(): WhereItem
    {
        return WhereClause::fromRaw([
            'stockLevel' => [Material::LEVEL_CRITICAL, Material::LEVEL_OUT],
        ]);
    }
}
