<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class VisitPhoto extends Entity
{
    public const ENTITY_TYPE = 'VisitPhoto';

    public const STAGE_BEFORE = 'before';
    public const STAGE_DURING = 'during';
    public const STAGE_AFTER = 'after';
}
