<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\Payment;

use Espo\Core\Exceptions\Conflict;
use Espo\Modules\EspoDental\Entities\Payment;
use Espo\ORM\Entity;

class PreventPostedMutation
{
    public static int $order = 1;

    /**
     * @param array<string, mixed> $options
     */
    public function beforeSave(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof Payment) {
            return;
        }

        if ($entity->isNew() || !empty($options['espodentalAllowPaymentMutation'])) {
            return;
        }

        if ($this->isPostedStatus((string) ($entity->getFetched('status') ?? ''))) {
            throw new Conflict('Posted payments are immutable; create a refund payment');
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    public function beforeRemove(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof Payment) {
            return;
        }

        if (!empty($options['espodentalAllowPaymentMutation'])) {
            return;
        }

        if ($this->isPostedStatus((string) ($entity->get('status') ?? ''))) {
            throw new Conflict('Posted payments are immutable; create a refund payment');
        }
    }

    private function isPostedStatus(string $status): bool
    {
        return in_array($status, [
            Payment::STATUS_COMPLETED,
            Payment::STATUS_REFUNDED,
        ], true);
    }
}
