<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\VisitMaterialLine;

use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\Material;
use Espo\Modules\EspoDental\Entities\VisitMaterialLine;
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
        if (!$entity instanceof VisitMaterialLine) {
            return;
        }

        $materialId = $entity->getMaterialId();
        if ($materialId) {
            /** @var Material|null $material */
            $material = $this->entityManager->getEntityById(Material::ENTITY_TYPE, $materialId);

            if ($material) {
                $materialChanged = $entity->get('materialId') !== $entity->getFetched('materialId');

                $entity->set('unit', (string) $material->get('unit'));
                $entity->set('unitPrice', (float) $material->get('price'));
                $entity->set('unitPriceCurrency', (string) ($material->get('priceCurrency') ?: 'RUB'));

                if ($materialChanged || !$entity->get('name')) {
                    $entity->set('name', (string) $material->get('name'));
                }
            }
        }

        $quantity = max(0.0, $entity->getQuantity());
        $unitPrice = max(0.0, $entity->getUnitPrice());

        $entity->set('quantity', $quantity);
        $entity->set('unitPrice', $unitPrice);
        $entity->set('totalCost', round($quantity * $unitPrice, 2));
        $currency = (string) ($entity->get('unitPriceCurrency') ?: 'RUB');
        $entity->set('totalCostCurrency', $currency);
        if ($currency === 'RUB') {
            $entity->set('unitPriceConverted', $unitPrice);
            $entity->set('totalCostConverted', round($quantity * $unitPrice, 2));
        }
    }
}
