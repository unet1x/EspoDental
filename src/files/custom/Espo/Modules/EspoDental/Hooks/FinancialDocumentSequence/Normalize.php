<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\FinancialDocumentSequence;

use Espo\Modules\EspoDental\Entities\FinancialDocumentSequence;
use Espo\ORM\Entity;

class Normalize
{
    public static int $order = 5;

    /**
     * @param array<string, mixed> $options
     */
    public function beforeSave(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof FinancialDocumentSequence) {
            return;
        }

        $entity->set('name', (string) $entity->get('documentType') . ' ' . (string) $entity->get('prefix'));
    }
}
