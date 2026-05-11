<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Classes\Select\Material\BoolFilters;

use Espo\Core\Select\Bool\Filter;
use Espo\Entities\User;
use Espo\Modules\EspoDental\Entities\Material;
use Espo\ORM\Query\Part\WhereClause;
use Espo\ORM\Query\Part\WhereItem;

class CriticalStock implements Filter
{
    public function apply(User $user): ?WhereItem
    {
        return WhereClause::fromRaw([
            'stockLevel' => [Material::LEVEL_CRITICAL, Material::LEVEL_OUT],
        ]);
    }
}
