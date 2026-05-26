<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\FinancialAdjustment;

use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\EspoDental\Entities\FinancialAdjustment;
use Espo\ORM\Entity;

class Normalize
{
    public static int $order = 5;

    /**
     * @param array<string, mixed> $options
     */
    public function beforeSave(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof FinancialAdjustment) {
            return;
        }

        if (trim((string) ($entity->get('reason') ?? '')) === '') {
            throw new BadRequest('Financial adjustment reason is required');
        }

        $amount = round((float) ($entity->get('amount') ?? 0), 2);
        if ($amount <= 0.0) {
            throw new BadRequest('Financial adjustment amount must be positive');
        }

        $type = (string) $entity->get('type');
        $sign = $type === FinancialAdjustment::TYPE_MANUAL_CHARGE ? 1.0 : -1.0;
        $entity->set('signedAmount', $sign * $amount);
        $entity->set('name', $type . ' ' . number_format($amount, 2, '.', ''));
    }
}
