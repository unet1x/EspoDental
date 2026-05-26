<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class IntegrationSettings extends Entity
{
    public const ENTITY_TYPE = 'IntegrationSettings';

    public const TYPE_SMTP = 'smtp';
    public const TYPE_WHATSAPP = 'whatsapp';
    public const TYPE_TELEGRAM = 'telegram';
}
