<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class Visit extends Entity
{
    public const ENTITY_TYPE = 'Visit';

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_FINISHED = 'finished';
    public const STATUS_CANCELLED = 'cancelled';

    public function getStatus(): ?string
    {
        return $this->get('status');
    }

    public function getPatientId(): ?string
    {
        return $this->get('patientId');
    }

    public function getDoctorId(): ?string
    {
        return $this->get('doctorId');
    }

    public function getAppointmentId(): ?string
    {
        return $this->get('appointmentId');
    }
}
