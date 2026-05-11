<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class OrthodonticCard extends Entity
{
    public const ENTITY_TYPE = 'OrthodonticCard';

    public const STATUS_OPEN = 'open';
    public const STATUS_IN_TREATMENT = 'in_treatment';
    public const STATUS_RETENTION = 'retention';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const APPARATUS_BRACKETS_METAL = 'brackets_metal';
    public const APPARATUS_BRACKETS_CERAMIC = 'brackets_ceramic';
    public const APPARATUS_BRACKETS_LINGUAL = 'brackets_lingual';
    public const APPARATUS_ALIGNERS = 'aligners';

    public const ACTIVE_STATUSES = [self::STATUS_OPEN, self::STATUS_IN_TREATMENT, self::STATUS_RETENTION];

    public function getStatus(): string
    {
        return (string) $this->get('status', self::STATUS_OPEN);
    }

    public function getCardNumber(): ?string
    {
        return $this->get('cardNumber');
    }

    public function isActive(): bool
    {
        return in_array($this->getStatus(), self::ACTIVE_STATUSES, true);
    }
}
