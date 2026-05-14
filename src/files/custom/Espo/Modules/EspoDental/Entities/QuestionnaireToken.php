<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use DateTimeImmutable;
use Espo\Core\ORM\Entity;

class QuestionnaireToken extends Entity
{
    public const ENTITY_TYPE = 'QuestionnaireToken';

    public function getToken(): ?string
    {
        return $this->get('token');
    }

    public function getPatientId(): ?string
    {
        return $this->get('patientId');
    }

    public function getPreliminaryPatientId(): ?string
    {
        return $this->get('preliminaryPatientId');
    }

    public function getLanguage(): string
    {
        return (string) ($this->get('language') ?? 'ru_RU');
    }

    public function isUsed(): bool
    {
        return (bool) $this->get('isUsed');
    }

    public function isExpired(?DateTimeImmutable $now = null): bool
    {
        $expiresAt = $this->get('expiresAt');
        if (!$expiresAt) {
            return true;
        }
        $now ??= new DateTimeImmutable();
        try {
            $expires = new DateTimeImmutable($expiresAt);
        } catch (\Exception) {
            return true;
        }
        return $now > $expires;
    }
}
