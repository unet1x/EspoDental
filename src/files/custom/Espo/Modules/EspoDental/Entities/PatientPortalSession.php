<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use DateTimeImmutable;
use Espo\Core\ORM\Entity;

class PatientPortalSession extends Entity
{
    public const ENTITY_TYPE = 'PatientPortalSession';

    public const CONTACT_EMAIL = 'email';

    public function getPatientId(): ?string
    {
        return $this->get('patientId');
    }

    public function getContactValueSnapshot(): string
    {
        return (string) ($this->get('contactValueSnapshot') ?? '');
    }

    public function getTokenHash(): string
    {
        return (string) ($this->get('tokenHash') ?? '');
    }

    public function getOtpHash(): string
    {
        return (string) ($this->get('otpHash') ?? '');
    }

    public function isRevoked(): bool
    {
        return (bool) $this->get('revokedAt');
    }

    public function isExpired(?DateTimeImmutable $now = null): bool
    {
        $expiresAt = $this->get('expiresAt');
        if (!$expiresAt) {
            return true;
        }

        $now ??= new DateTimeImmutable();

        try {
            return $now > new DateTimeImmutable((string) $expiresAt);
        } catch (\Exception) {
            return true;
        }
    }
}
