<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class HealthQuestionnaire extends Entity
{
    public const ENTITY_TYPE = 'HealthQuestionnaire';

    public function getPatientId(): ?string
    {
        return $this->get('patientId');
    }

    public function getLanguage(): string
    {
        return (string) ($this->get('language') ?? 'ru_RU');
    }

    /**
     * @return array<string, mixed>
     */
    public function getItems(): array
    {
        $items = $this->get('items');
        if (is_object($items)) {
            $items = (array) $items;
        }
        return is_array($items) ? $items : [];
    }

    /**
     * @return array<int, string>
     */
    public function getAlertItems(): array
    {
        $alerts = $this->get('alertItems');
        return is_array($alerts) ? $alerts : [];
    }

    public function hasAlerts(): bool
    {
        return (bool) $this->get('hasAlerts');
    }

    public function isExpired(): bool
    {
        return (bool) $this->get('isExpired');
    }
}
