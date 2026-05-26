<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class AppointmentRescheduleRequest extends Entity
{
    public const ENTITY_TYPE = 'AppointmentRescheduleRequest';

    public const STATUS_PENDING = 'pending_clinic_confirmation';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SLOT_UNAVAILABLE = 'slot_unavailable';
    public const STATUS_CANCELLED_BY_PATIENT = 'cancelled_by_patient';
    public const STATUS_ESCALATED_TO_ADMIN = 'escalated_to_admin';

    /** @var list<string> */
    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ESCALATED_TO_ADMIN,
    ];

    public function getAppointmentId(): ?string
    {
        return $this->get('appointmentId');
    }

    public function getPatientId(): ?string
    {
        return $this->get('patientId');
    }

    public function getStatus(): string
    {
        return (string) ($this->get('status') ?? self::STATUS_PENDING);
    }

    public function isActive(): bool
    {
        return in_array($this->getStatus(), self::ACTIVE_STATUSES, true);
    }
}
