<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class SalaryBonus extends Entity
{
    public const ENTITY_TYPE = 'SalaryBonus';

    public const KIND_BONUS = 'bonus';
    public const KIND_ALLOWANCE = 'allowance';
    public const KIND_PENALTY = 'penalty';

    public const STATUS_PENDING = 'pending';
    public const STATUS_INCLUDED = 'included';
    public const STATUS_CANCELLED = 'cancelled';

    public function getKind(): string
    {
        return (string) $this->get('kind', self::KIND_BONUS);
    }

    public function getStatus(): string
    {
        return (string) $this->get('status', self::STATUS_PENDING);
    }

    public function getAmount(): float
    {
        return (float) $this->get('amount', 0);
    }

    public function isPenalty(): bool
    {
        return $this->getKind() === self::KIND_PENALTY;
    }
}
