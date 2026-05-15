<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\Visit;

use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\EspoDental\Entities\Visit;
use Espo\ORM\Entity;

class RequireAppointmentSource
{
    public static int $order = 5;

    /**
     * @param array<string, mixed> $options
     */
    public function beforeSave(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof Visit) {
            return;
        }

        if (!$entity->isNew()) {
            return;
        }

        if (!empty($options['espodentalAllowVisitCreate'])) {
            return;
        }

        throw new Forbidden('Visit must be started from an appointment');
    }
}
