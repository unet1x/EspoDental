<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class AppointmentStatusLog extends Entity
{
    public const ENTITY_TYPE = 'AppointmentStatusLog';

    public function getAppointmentId(): ?string
    {
        return $this->get('appointmentId');
    }

    public function getFromStatus(): ?string
    {
        return $this->get('fromStatus');
    }

    public function getToStatus(): ?string
    {
        return $this->get('toStatus');
    }
}
