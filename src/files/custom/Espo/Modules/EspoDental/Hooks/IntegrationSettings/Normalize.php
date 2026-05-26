<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\IntegrationSettings;

use DateTimeImmutable;
use Espo\Modules\EspoDental\Entities\IntegrationSettings;
use Espo\ORM\Entity;

class Normalize
{
    public static int $order = 5;

    /**
     * @param array<string, mixed> $options
     */
    public function beforeSave(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof IntegrationSettings) {
            return;
        }

        $entity->set('name', (string) $entity->get('integrationType'));
        $entity->set('updatedAt', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
    }
}
