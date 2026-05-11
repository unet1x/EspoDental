<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class Invoice extends Entity
{
    public const ENTITY_TYPE = 'Invoice';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_PARTIAL_PAID = 'partial_paid';
    public const STATUS_PAID = 'paid';
    public const STATUS_STORNO = 'storno';
    public const STATUS_CANCELLED = 'cancelled';

    public function getStatus(): ?string
    {
        return $this->get('status');
    }

    public function getTotalAmount(): float
    {
        return (float) $this->get('totalAmount');
    }

    public function getPaidAmount(): float
    {
        return (float) $this->get('paidAmount');
    }

    public function getBalance(): float
    {
        return (float) $this->get('balance');
    }

    public function getPatientId(): ?string
    {
        return $this->get('patientId');
    }
}
