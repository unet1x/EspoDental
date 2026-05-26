<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use Espo\Modules\EspoDental\Entities\IntegrationSecret;

class IntegrationSettingsService
{
    /**
     * @return array<string, mixed>
     */
    public function sanitizeSecret(IntegrationSecret $secret): array
    {
        return [
            'id' => (string) $secret->getId(),
            'clinicId' => (string) ($secret->get('clinicId') ?? ''),
            'name' => (string) ($secret->get('name') ?? ''),
            'secretKind' => (string) ($secret->get('secretKind') ?? ''),
            'valuePresent' => (bool) ($secret->get('valuePresent') ?? false),
            'updatedAt' => (string) ($secret->get('updatedAt') ?? ''),
        ];
    }
}
