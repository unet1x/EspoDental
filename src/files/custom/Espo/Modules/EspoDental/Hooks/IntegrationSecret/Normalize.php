<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\IntegrationSecret;

use DateTimeImmutable;
use Espo\Modules\EspoDental\Entities\IntegrationSecret;
use Espo\ORM\Entity;

class Normalize
{
    public static int $order = 5;

    /**
     * @param array<string, mixed> $options
     */
    public function beforeSave(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof IntegrationSecret) {
            return;
        }

        $entity->set('valuePresent', trim((string) ($entity->get('secretValue') ?? '')) !== '');
        $entity->set('updatedAt', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
    }
}
