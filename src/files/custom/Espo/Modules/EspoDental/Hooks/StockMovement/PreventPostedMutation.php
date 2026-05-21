<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\StockMovement;

use Espo\Core\Exceptions\Conflict;
use Espo\Modules\EspoDental\Entities\StockMovement;
use Espo\ORM\Entity;

class PreventPostedMutation
{
    public static int $order = 1;

    /**
     * @param array<string, mixed> $options
     */
    public function beforeSave(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof StockMovement) {
            return;
        }

        if ($entity->isNew() || !empty($options['espodentalAllowStockMovementMutation'])) {
            return;
        }

        throw new Conflict('Posted stock movements are immutable; create a correction movement');
    }

    /**
     * @param array<string, mixed> $options
     */
    public function beforeRemove(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof StockMovement) {
            return;
        }

        if (!empty($options['espodentalAllowStockMovementMutation'])) {
            return;
        }

        throw new Conflict('Posted stock movements are immutable; create a correction movement');
    }
}
