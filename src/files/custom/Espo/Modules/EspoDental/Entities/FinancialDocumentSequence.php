<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class FinancialDocumentSequence extends Entity
{
    public const ENTITY_TYPE = 'FinancialDocumentSequence';

    public const DOCUMENT_INVOICE = 'invoice';
    public const DOCUMENT_ACT = 'act';
    public const DOCUMENT_RECEIPT = 'receipt';
}
