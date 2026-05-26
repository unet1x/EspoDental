<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\CashShift;

use Espo\Modules\EspoDental\Entities\CashShift;
use Espo\ORM\Entity;

class Normalize
{
    public static int $order = 5;

    /**
     * @param array<string, mixed> $options
     */
    public function beforeSave(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof CashShift || $entity->get('name')) {
            return;
        }

        $entity->set('name', 'Cash shift ' . (string) ($entity->get('openedAt') ?? ''));
    }
}
