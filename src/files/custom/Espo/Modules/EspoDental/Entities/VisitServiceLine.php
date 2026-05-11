<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class VisitServiceLine extends Entity
{
    public const ENTITY_TYPE = 'VisitServiceLine';

    public function getQuantity(): int
    {
        return (int) $this->get('quantity');
    }

    public function getUnitPrice(): float
    {
        return (float) $this->get('unitPrice');
    }

    public function getDiscount(): float
    {
        return (float) $this->get('discount');
    }

    public function getVatRate(): float
    {
        return (float) $this->get('vatRate');
    }

    public function getAmount(): float
    {
        return (float) $this->get('amount');
    }

    public function getVisitId(): ?string
    {
        return $this->get('visitId');
    }
}
