<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Entities;

use Espo\Core\ORM\Entity;

class NotificationLog extends Entity
{
    public const ENTITY_TYPE = 'NotificationLog';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_TELEGRAM = 'telegram';
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_INTERNAL = 'internal';

    public const KIND_REMINDER = 'appointment_reminder';
    public const KIND_QUESTIONNAIRE = 'questionnaire_invite';
    public const KIND_LOW_STOCK = 'low_stock';
    public const KIND_MANUAL = 'manual';

    public function getChannel(): ?string
    {
        return $this->get('channel');
    }

    public function getStatus(): ?string
    {
        return $this->get('status');
    }
}
