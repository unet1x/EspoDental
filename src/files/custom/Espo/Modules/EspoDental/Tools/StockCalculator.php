<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Tools;

use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\Material;
use Espo\Modules\EspoDental\Entities\StockMovement;

class StockCalculator
{
    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    public function recalculate(Material $material): void
    {
        /** @var iterable<StockMovement> $movements */
        $movements = $this->entityManager
            ->getRDBRepository(StockMovement::ENTITY_TYPE)
            ->where(['materialId' => $material->getId()])
            ->find();

        $total = 0.0;
        foreach ($movements as $movement) {
            $total += $movement->getSignedQuantity();
        }
        $total = round($total, 4);

        $material->set('currentStock', $total);
        $material->set('stockLevel', $material->computeLevel());
    }
}
