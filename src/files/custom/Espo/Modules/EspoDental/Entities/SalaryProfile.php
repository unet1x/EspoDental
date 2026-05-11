<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class SalaryProfile extends Entity
{
    public const ENTITY_TYPE = 'SalaryProfile';

    public const ROLE_DOCTOR = 'doctor';
    public const ROLE_ASSISTANT = 'assistant';
    public const ROLE_ADMINISTRATOR = 'administrator';
    public const ROLE_STOCK_MANAGER = 'stock_manager';
    public const ROLE_MANAGER = 'manager';

    public const RATE_FIXED_MONTHLY = 'fixed_monthly';
    public const RATE_HOURLY = 'hourly';
    public const RATE_PER_VISIT = 'per_visit';
    public const RATE_NONE = 'none';

    public function isActive(): bool
    {
        return (bool) $this->get('isActive', true);
    }

    public function getRoleType(): string
    {
        return (string) $this->get('roleType', self::ROLE_DOCTOR);
    }

    public function getRateType(): string
    {
        return (string) $this->get('rateType', self::RATE_FIXED_MONTHLY);
    }

    public function getBaseRate(): float
    {
        return (float) $this->get('baseRate', 0);
    }

    public function getRevenuePercent(): float
    {
        return (float) $this->get('revenuePercent', 0);
    }

    public function getAssistantPercent(): float
    {
        return (float) $this->get('assistantPercent', 0);
    }

    public function getCurrency(): string
    {
        return (string) $this->get('currency', 'RUB');
    }
}
