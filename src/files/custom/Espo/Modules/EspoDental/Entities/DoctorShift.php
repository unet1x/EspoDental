<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class DoctorShift extends Entity
{
    public const ENTITY_TYPE = 'DoctorShift';

    public const TYPE_REGULAR = 'regular';
    public const TYPE_ADDITIONAL = 'additional';
    public const TYPE_CLOSED = 'closed';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELLED = 'cancelled';

    public function getDoctorId(): ?string
    {
        return $this->get('doctorId');
    }

    public function getAssistantId(): ?string
    {
        return $this->get('assistantId');
    }

    public function getClinicId(): ?string
    {
        return $this->get('clinicId');
    }

    public function getCabinetId(): ?string
    {
        return $this->get('cabinetId');
    }

    public function getDateStart(): ?string
    {
        return $this->get('dateStart');
    }

    public function getDateEnd(): ?string
    {
        return $this->get('dateEnd');
    }

    public function getType(): string
    {
        return (string) ($this->get('type') ?: self::TYPE_REGULAR);
    }

    public function getStatus(): string
    {
        return (string) ($this->get('status') ?: self::STATUS_ACTIVE);
    }
}
