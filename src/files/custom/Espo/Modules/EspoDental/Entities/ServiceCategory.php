<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class ServiceCategory extends Entity
{
    public const ENTITY_TYPE = 'ServiceCategory';

    public function isActive(): bool
    {
        return (bool) $this->get('isActive');
    }
}
