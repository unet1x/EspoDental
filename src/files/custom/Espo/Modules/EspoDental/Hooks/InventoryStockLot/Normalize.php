<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\InventoryStockLot;

use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\InventoryStockLot;
use Espo\Modules\EspoDental\Entities\Material;
use Espo\ORM\Entity;

class Normalize
{
    public static int $order = 5;

    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function beforeSave(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof InventoryStockLot || $entity->get('name')) {
            return;
        }

        $materialName = '';
        if ($entity->get('materialId')) {
            /** @var Material|null $material */
            $material = $this->entityManager->getEntityById(Material::ENTITY_TYPE, (string) $entity->get('materialId'));
            $materialName = $material ? (string) $material->get('name') : '';
        }

        $label = trim($materialName . ' ' . (string) ($entity->get('lotNumber') ?? ''));
        $entity->set('name', $label !== '' ? $label : 'Stock lot');
    }
}
