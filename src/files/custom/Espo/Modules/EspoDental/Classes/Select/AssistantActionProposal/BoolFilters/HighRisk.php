<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Classes\Select\AssistantActionProposal\BoolFilters;

use Espo\Core\Select\Bool\Filter;
use Espo\Entities\User;
use Espo\Modules\EspoDental\Entities\AssistantActionProposal;
use Espo\ORM\Query\Part\WhereClause;
use Espo\ORM\Query\Part\WhereItem;

class HighRisk implements Filter
{
    public function apply(User $user): WhereItem
    {
        return WhereClause::fromRaw([
            'riskLevel' => [
                AssistantActionProposal::RISK_HIGH,
                AssistantActionProposal::RISK_CRITICAL,
            ],
        ]);
    }
}
