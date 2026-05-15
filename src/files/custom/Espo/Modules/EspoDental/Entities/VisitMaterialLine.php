<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class VisitMaterialLine extends Entity
{
    public const ENTITY_TYPE = 'VisitMaterialLine';

    public function getVisitId(): ?string
    {
        return $this->get('visitId');
    }

    public function getVisitServiceLineId(): ?string
    {
        return $this->get('visitServiceLineId');
    }

    public function getMaterialId(): ?string
    {
        return $this->get('materialId');
    }

    public function getQuantity(): float
    {
        return (float) $this->get('quantity');
    }

    public function getUnitPrice(): float
    {
        return (float) $this->get('unitPrice');
    }
}
