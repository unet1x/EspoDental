<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class Payment extends Entity
{
    public const ENTITY_TYPE = 'Payment';

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';

    public const DIRECTION_IN = 'in';
    public const DIRECTION_OUT = 'out';

    public const METHOD_CASH = 'cash';
    public const METHOD_CARD = 'card';
    public const METHOD_BANK_TRANSFER = 'bank_transfer';
    public const METHOD_ONLINE = 'online';
    public const METHOD_TERMINAL = 'terminal';
    public const METHOD_CRYPTO = 'crypto';
    public const METHOD_ADVANCE = 'advance';
    public const METHOD_OTHER = 'other';

    public const METHOD_LIST = [
        self::METHOD_CASH,
        self::METHOD_CARD,
        self::METHOD_BANK_TRANSFER,
        self::METHOD_ONLINE,
        self::METHOD_TERMINAL,
        self::METHOD_CRYPTO,
        self::METHOD_ADVANCE,
        self::METHOD_OTHER,
    ];

    public function getAmount(): float
    {
        return (float) $this->get('amount');
    }

    public function getStatus(): ?string
    {
        return $this->get('status');
    }

    public function getDirection(): ?string
    {
        return $this->get('direction');
    }

    public function getInvoiceId(): ?string
    {
        return $this->get('invoiceId');
    }

    public function getPatientId(): ?string
    {
        return $this->get('patientId');
    }

    public function isInbound(): bool
    {
        return $this->getDirection() === self::DIRECTION_IN
            && $this->getStatus() === self::STATUS_COMPLETED;
    }
}
