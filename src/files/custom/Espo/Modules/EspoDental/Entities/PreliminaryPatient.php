<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class PreliminaryPatient extends Entity
{
    public const ENTITY_TYPE = 'PreliminaryPatient';

    public const STATUS_ENTERED = 'entered';
    public const STATUS_BOOKED = 'booked';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_NO_SHOW = 'no_show';

    public function getStatus(): ?string
    {
        return $this->get('status');
    }

    public function isConverted(): bool
    {
        return $this->get('convertedToPatientId') !== null;
    }

    public function getClinicId(): ?string
    {
        return $this->get('clinicId');
    }

    public function getPhoneNumber(): ?string
    {
        return $this->get('phoneNumber') ?? $this->get('phone');
    }

    public function getEmailAddress(): ?string
    {
        return $this->get('emailAddress');
    }
}
