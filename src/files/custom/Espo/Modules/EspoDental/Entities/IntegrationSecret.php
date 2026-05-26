<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class IntegrationSecret extends Entity
{
    public const ENTITY_TYPE = 'IntegrationSecret';

    public const KIND_PROVIDER_TOKEN = 'provider_token';
    public const KIND_SMTP_PASSWORD = 'smtp_password';
    public const KIND_API_KEY = 'api_key';
}
