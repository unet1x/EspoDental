<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\Invoice;

use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\EspoDental\Entities\Invoice;
use Espo\ORM\Entity;

class RequireVisitSource
{
    public static int $order = 5;

    /**
     * @param array<string, mixed> $options
     */
    public function beforeSave(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof Invoice) {
            return;
        }

        if (!$entity->isNew()) {
            return;
        }

        if (!empty($options['espodentalAllowInvoiceCreate'])) {
            return;
        }

        throw new Forbidden('Invoice must be created from a visit');
    }
}
