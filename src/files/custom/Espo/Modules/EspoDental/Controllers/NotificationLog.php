<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Controllers;

use DateTimeImmutable;
use Espo\Core\Api\Request;
use Espo\Core\Controllers\Record;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\EspoDental\Entities\NotificationLog as NotificationLogEntity;
use Espo\Modules\EspoDental\Services\NotificationDeliveryService;

class NotificationLog extends Record
{
    private const MAX_REQUEUE_ATTEMPTS = 3;

    /**
     * POST /EspoDental/NotificationLog/requeue
     *
     * @return array{id: string, status: string, attempts: int, scheduledFor: string}
     */
    public function postActionRequeue(Request $request): array
    {
        if (!$this->getAcl()->checkScope(NotificationLogEntity::ENTITY_TYPE, 'edit')) {
            throw new Forbidden();
        }

        $body = $request->getParsedBody();
        if (!is_object($body)) {
            throw new BadRequest('Invalid payload');
        }

        $id = isset($body->id) ? (string) $body->id : '';
        if ($id === '') {
            throw new BadRequest('id is required');
        }

        /** @var NotificationLogEntity|null $log */
        $log = $this->entityManager->getEntityById(NotificationLogEntity::ENTITY_TYPE, $id);
        if (!$log) {
            throw new NotFound('Notification log not found');
        }

        if ((string) ($log->get('status') ?? '') !== NotificationLogEntity::STATUS_FAILED) {
            throw new Conflict('Only failed notifications can be requeued');
        }

        $attempts = (int) ($log->get('attempts') ?? 0);
        if ($attempts >= self::MAX_REQUEUE_ATTEMPTS) {
            throw new Conflict('Notification retry limit reached');
        }

        $scheduledFor = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $payload = $this->buildRequeuePayload($log, $body, $scheduledFor);

        $log->set('status', NotificationLogEntity::STATUS_QUEUED);
        $log->set('scheduledFor', $scheduledFor);
        $log->set('sentAt', null);
        $log->set('externalMessageId', null);
        $log->set('errorMessage', null);
        $log->set('payload', $payload);

        $this->entityManager->saveEntity($log);

        return [
            'id' => (string) $log->getId(),
            'status' => (string) $log->get('status'),
            'attempts' => (int) ($log->get('attempts') ?? 0),
            'scheduledFor' => (string) ($log->get('scheduledFor') ?? $scheduledFor),
        ];
    }

    /**
     * POST /EspoDental/NotificationLog/processQueue
     *
     * @return array<string, mixed>
     */
    public function postActionProcessQueue(Request $request): array
    {
        if (!$this->getAcl()->checkScope(NotificationLogEntity::ENTITY_TYPE, 'edit')) {
            throw new Forbidden();
        }

        $body = $request->getParsedBody();
        if (!is_object($body)) {
            throw new BadRequest('Invalid payload');
        }

        /** @var NotificationDeliveryService $service */
        $service = $this->injectableFactory->create(NotificationDeliveryService::class);

        $id = isset($body->id) ? (string) $body->id : '';
        if ($id !== '') {
            $row = $service->processOne($id);

            return [
                'processed' => 1,
                'sent' => $row['status'] === NotificationLogEntity::STATUS_SENT ? 1 : 0,
                'failed' => $row['status'] === NotificationLogEntity::STATUS_FAILED ? 1 : 0,
                'skipped' => 0,
                'rows' => [$row],
            ];
        }

        return $service->processQueued((int) ($body->limit ?? 5));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRequeuePayload(NotificationLogEntity $log, object $body, string $scheduledFor): array
    {
        $payload = $log->get('payload');
        if (is_object($payload)) {
            $payload = (array) $payload;
        }

        if (!is_array($payload)) {
            $payload = [];
        }

        $history = $payload['requeueHistory'] ?? [];
        if (!is_array($history)) {
            $history = [];
        }

        $history[] = [
            'requestedAt' => $scheduledFor,
            'requestedById' => (string) $this->getUser()->getId(),
            'previousStatus' => (string) ($log->get('status') ?? ''),
            'previousErrorMessage' => (string) ($log->get('errorMessage') ?? ''),
            'previousAttempts' => (int) ($log->get('attempts') ?? 0),
            'note' => property_exists($body, 'note') ? trim((string) $body->note) : '',
        ];

        $payload['requeueHistory'] = $history;

        return $payload;
    }
}
