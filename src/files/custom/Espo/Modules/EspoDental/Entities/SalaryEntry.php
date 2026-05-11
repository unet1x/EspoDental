<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class SalaryEntry extends Entity
{
    public const ENTITY_TYPE = 'SalaryEntry';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    public function getStatus(): string
    {
        return (string) $this->get('status', self::STATUS_DRAFT);
    }

    public function getCurrency(): string
    {
        return (string) $this->get('currency', 'RUB');
    }

    public function getTotalAmount(): float
    {
        return (float) $this->get('totalAmount', 0);
    }

    public function getBaseAmount(): float
    {
        return (float) $this->get('baseAmount', 0);
    }

    public function getRevenueAmount(): float
    {
        return (float) $this->get('revenueAmount', 0);
    }

    public function getAssistantAmount(): float
    {
        return (float) $this->get('assistantAmount', 0);
    }

    public function getBonusAmount(): float
    {
        return (float) $this->get('bonusAmount', 0);
    }

    public function getDeductionAmount(): float
    {
        return (float) $this->get('deductionAmount', 0);
    }
}
