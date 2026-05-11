<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class TreatmentStage extends Entity
{
    public const ENTITY_TYPE = 'TreatmentStage';

    public const STATUS_PLANNED = 'planned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_SKIPPED = 'skipped';

    public function getStatus(): string
    {
        return (string) $this->get('status', self::STATUS_PLANNED);
    }
}
