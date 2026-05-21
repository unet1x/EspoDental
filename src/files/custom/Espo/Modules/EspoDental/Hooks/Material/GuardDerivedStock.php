<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\Material;

use Espo\Core\Exceptions\Conflict;
use Espo\Modules\EspoDental\Entities\Material;
use Espo\ORM\Entity;

class GuardDerivedStock
{
    public static int $order = 1;

    /**
     * @param array<string, mixed> $options
     */
    public function beforeSave(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof Material) {
            return;
        }

        if (!empty($options['espodentalAllowDerivedStockUpdate'])) {
            return;
        }

        if ($entity->isNew()) {
            $entity->set('currentStock', 0.0);
            $entity->set('stockLevel', Material::LEVEL_OUT);

            return;
        }

        if (
            $this->fieldChanged($entity, 'currentStock') ||
            $this->fieldChanged($entity, 'stockLevel')
        ) {
            throw new Conflict('Material stock is derived from StockMovement records');
        }
    }

    private function fieldChanged(Material $material, string $field): bool
    {
        return $material->get($field) !== $material->getFetched($field);
    }
}
