<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\Patient;

use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\EspoDental\Entities\Patient;
use Espo\ORM\Entity;

class RequireConversionSource
{
    public static int $order = 5;

    /**
     * @param array<string, mixed> $options
     */
    public function beforeSave(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof Patient) {
            return;
        }

        if (!$entity->isNew()) {
            return;
        }

        if (!empty($options['espodentalAllowPatientCreate'])) {
            return;
        }

        throw new Forbidden('Patient must be created from a preliminary patient conversion');
    }
}
