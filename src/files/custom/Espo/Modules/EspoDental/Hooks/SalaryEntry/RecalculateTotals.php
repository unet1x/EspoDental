<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\SalaryEntry;

use Espo\Modules\EspoDental\Entities\SalaryEntry;
use Espo\ORM\Entity;

class RecalculateTotals
{
    public static int $order = 5;

    /**
     * @param array<string, mixed> $options
     */
    public function beforeSave(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof SalaryEntry) {
            return;
        }
        $total = $entity->getBaseAmount()
            + $entity->getRevenueAmount()
            + $entity->getAssistantAmount()
            + $entity->getBonusAmount()
            - $entity->getDeductionAmount();
        $entity->set('totalAmount', round($total, 2));
    }
}
