<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class Clinic extends Entity
{
    public const ENTITY_TYPE = 'Clinic';

    public function getName(): ?string
    {
        return $this->get('name');
    }

    public function getCode(): ?string
    {
        return $this->get('code');
    }

    public function getTimezone(): ?string
    {
        return $this->get('timezone');
    }

    public function getDefaultCurrency(): ?string
    {
        return $this->get('defaultCurrency');
    }

    public function isActive(): bool
    {
        return (bool) $this->get('isActive');
    }
}
