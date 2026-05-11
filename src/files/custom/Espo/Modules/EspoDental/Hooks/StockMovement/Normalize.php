<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\StockMovement;

use DateTimeImmutable;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\Material;
use Espo\Modules\EspoDental\Entities\StockMovement;
use Espo\Modules\EspoDental\Tools\StockCalculator;
use Espo\ORM\Entity;

class Normalize
{
    public static int $order = 5;

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly StockCalculator $calculator
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function beforeSave(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof StockMovement) {
            return;
        }

        $type = (string) $entity->get('type');
        if ($type === '') {
            throw new BadRequest('Type is required');
        }

        $entity->set('direction', StockMovement::deriveDirection($type));

        $qty = $entity->getQuantity();
        $unitPrice = (float) $entity->get('unitPrice');
        $entity->set('totalCost', round($qty * $unitPrice, 2));

        if (!$entity->get('performedAt')) {
            $entity->set('performedAt', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
        }

        if (!$entity->get('name') && $entity->getMaterialId()) {
            /** @var Material|null $m */
            $m = $this->entityManager->getEntityById(Material::ENTITY_TYPE, $entity->getMaterialId());
            if ($m) {
                $sign = $entity->getDirection() === StockMovement::DIRECTION_IN ? '+' : '-';
                $entity->set('name', $sign . $qty . ' ' . (string) $m->get('unit') . ' · ' . (string) $m->get('name'));
                if (!$entity->get('unit')) {
                    $entity->set('unit', (string) $m->get('unit'));
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    public function afterSave(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof StockMovement) {
            return;
        }
        if (!empty($options['skipStockRecalc'])) {
            return;
        }
        $materialId = $entity->getMaterialId();
        if (!$materialId) {
            return;
        }
        /** @var Material|null $material */
        $material = $this->entityManager->getEntityById(Material::ENTITY_TYPE, $materialId);
        if ($material) {
            $this->calculator->recalculate($material);
            $this->entityManager->saveEntity($material, ['skipHooks' => true]);
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    public function afterRemove(Entity $entity, array $options = []): void
    {
        $this->afterSave($entity, $options);
    }
}
