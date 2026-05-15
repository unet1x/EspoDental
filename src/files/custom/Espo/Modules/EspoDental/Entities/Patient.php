<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class Patient extends Entity
{
    public const ENTITY_TYPE = 'Patient';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_ARCHIVED = 'archived';

    public function isVip(): bool
    {
        return (bool) $this->get('vip');
    }

    public function hasRestrictions(): bool
    {
        return (bool) $this->get('restrictions');
    }

    public function isChild(): bool
    {
        return (bool) $this->get('isChild');
    }

    public function getCardNumber(): ?string
    {
        return $this->get('cardNumber');
    }

    public function getClinicId(): ?string
    {
        return $this->get('clinicId');
    }

    public function getParentPatientId(): ?string
    {
        return $this->get('parentPatientId');
    }

    public function getStatus(): ?string
    {
        return $this->get('status');
    }

    public function getBalance(): float
    {
        return (float) ($this->get('balance') ?? 0.0);
    }

    public function getFirstVisitDate(): ?string
    {
        return $this->get('firstVisitDate');
    }
}
