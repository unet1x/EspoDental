<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class Appointment extends Entity
{
    public const ENTITY_TYPE = 'Appointment';

    public const STATUS_PLANNED = 'planned';
    public const STATUS_RESCHEDULED = 'rescheduled';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_ARRIVED = 'arrived';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_FINISHED = 'finished';
    public const STATUS_NO_SHOW = 'no_show';

    public const BLOCKING_STATUSES = [
        self::STATUS_PLANNED,
        self::STATUS_RESCHEDULED,
        self::STATUS_ARRIVED,
        self::STATUS_IN_PROGRESS,
    ];

    public function getStatus(): ?string
    {
        return $this->get('status');
    }

    public function getDoctorId(): ?string
    {
        return $this->get('doctorId');
    }

    public function getCabinetId(): ?string
    {
        return $this->get('cabinetId');
    }

    public function getClinicId(): ?string
    {
        return $this->get('clinicId');
    }

    public function getParentType(): ?string
    {
        return $this->get('parentType');
    }

    public function getParentId(): ?string
    {
        return $this->get('parentId');
    }

    public function getDateStart(): ?string
    {
        return $this->get('dateStart');
    }

    public function getDateEnd(): ?string
    {
        return $this->get('dateEnd');
    }

    public function isBlocking(): bool
    {
        return in_array($this->getStatus(), self::BLOCKING_STATUSES, true);
    }
}
