<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class InvoiceLine extends Entity
{
    public const ENTITY_TYPE = 'InvoiceLine';

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

    public function getVatAmount(): float
    {
        return (float) $this->get('vatAmount');
    }

    public function getInvoiceId(): ?string
    {
        return $this->get('invoiceId');
    }
}
