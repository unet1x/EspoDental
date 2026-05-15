<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\Patient;

use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\EspoDental\Entities\Patient;
use Espo\ORM\Entity;

class PreventManualRemove
{
    public static int $order = 1;

    /**
     * @param array<string, mixed> $options
     */
    public function beforeRemove(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof Patient) {
            return;
        }

        if (!empty($options['espodentalAllowPatientRemove'])) {
            return;
        }

        throw new Forbidden('Patients cannot be manually removed');
    }
}
