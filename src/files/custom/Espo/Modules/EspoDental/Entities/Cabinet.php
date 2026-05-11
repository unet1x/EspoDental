<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class Cabinet extends Entity
{
    public const ENTITY_TYPE = 'Cabinet';

    public function getName(): ?string
    {
        return $this->get('name');
    }

    public function getClinicId(): ?string
    {
        return $this->get('clinicId');
    }

    public function isActive(): bool
    {
        return (bool) $this->get('isActive');
    }
}
