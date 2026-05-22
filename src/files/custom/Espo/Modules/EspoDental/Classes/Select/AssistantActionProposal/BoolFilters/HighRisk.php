<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Classes\Select\AssistantActionProposal\BoolFilters;

use Espo\Modules\EspoDental\Classes\Select\Common\RawBoolFilter;
use Espo\Modules\EspoDental\Entities\AssistantActionProposal;
use Espo\ORM\Query\Part\WhereClause;
use Espo\ORM\Query\Part\WhereItem;

class HighRisk extends RawBoolFilter
{
    protected function buildWhereItem(): WhereItem
    {
        return WhereClause::fromRaw([
            'riskLevel' => [
                AssistantActionProposal::RISK_HIGH,
                AssistantActionProposal::RISK_CRITICAL,
            ],
        ]);
    }
}
