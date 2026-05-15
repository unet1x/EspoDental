<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\PreliminaryPatient;

use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\EspoDental\Entities\PreliminaryPatient;
use Espo\ORM\Entity;

class PreventManualRemove
{
    public static int $order = 1;

    /**
     * @param array<string, mixed> $options
     */
    public function beforeRemove(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof PreliminaryPatient) {
            return;
        }

        if (!empty($options['espodentalAllowPreliminaryPatientRemove'])) {
            return;
        }

        throw new Forbidden('Preliminary patients cannot be manually removed');
    }
}
