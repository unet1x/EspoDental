<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class ToothMovementPlan extends Entity
{
    public const ENTITY_TYPE = 'ToothMovementPlan';

    public const STATUS_PLANNED = 'planned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';

    public function getToothNumber(): ?string
    {
        return $this->get('toothNumber');
    }

    public function getStatus(): string
    {
        return (string) $this->get('status', self::STATUS_PLANNED);
    }
}
