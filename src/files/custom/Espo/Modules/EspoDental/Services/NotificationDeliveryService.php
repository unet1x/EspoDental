<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\NotificationLog;
use Espo\Modules\EspoDental\Tools\Messaging\MessageDeliveryGateway;

class NotificationDeliveryService
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly MessageDeliveryGateway $messageDeliveryGateway
    ) {
    }

    /**
     * @return array{processed: int, sent: int, failed: int, skipped: int, rows: list<array<string, mixed>>}
     */
    public function processQueued(int $limit = 5): array
    {
        $limit = max(1, min(25, $limit));
        $stats = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'rows' => []];
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        /** @var iterable<NotificationLog> $logs */
        $logs = $this->entityManager
            ->getRDBRepository(NotificationLog::ENTITY_TYPE)
            ->where(['deleted' => false, 'status' => NotificationLog::STATUS_QUEUED])
            ->order('scheduledFor', 'ASC')
            ->find();

        foreach ($logs as $log) {
            if (($stats['processed'] + $stats['skipped']) >= $limit) {
                break;
            }

            $scheduledFor = (string) ($log->get('scheduledFor') ?? '');
            if ($scheduledFor !== '' && $scheduledFor > $now) {
                $stats['skipped']++;
                continue;
            }

            try {
                $row = $this->deliver($log);
            } catch (Conflict $e) {
                $stats['skipped']++;
                continue;
            }

            if ($row['status'] === NotificationLog::STATUS_SKIPPED) {
                $stats['skipped']++;
                if (count($stats['rows']) < $limit) {
                    $stats['rows'][] = $row;
                }
                continue;
            }

            $stats['processed']++;
            $stats[$row['status'] === NotificationLog::STATUS_SENT ? 'sent' : 'failed']++;
            if (count($stats['rows']) < $limit) {
                $stats['rows'][] = $row;
            }
        }

        return $stats;
    }

    /**
     * @return array<string, mixed>
     */
    public function processOne(string $id): array
    {
        /** @var NotificationLog|null $log */
        $log = $this->entityManager->getEntityById(NotificationLog::ENTITY_TYPE, $id);
        if (!$log) {
            throw new NotFound('Notification log not found');
        }

        return $this->deliver($log);
    }

    /**
     * @return array<string, mixed>
     */
    private function deliver(NotificationLog $log): array
    {
        if ((string) ($log->get('status') ?? '') !== NotificationLog::STATUS_QUEUED) {
            throw new Conflict('Only queued notifications can be processed');
        }

        $direction = (string) ($log->get('direction') ?? NotificationLog::DIRECTION_OUTBOUND);
        if ($direction !== NotificationLog::DIRECTION_OUTBOUND) {
            throw new Conflict('Only outbound notifications can be processed');
        }

        $attempts = (int) ($log->get('attempts') ?? 0);
        if ($attempts >= self::MAX_ATTEMPTS) {
            throw new Conflict('Notification retry limit reached');
        }

        $preflight = $this->messageDeliveryGateway->preflight($log);
        if (!$preflight['ok']) {
            return [
                'id' => (string) $log->getId(),
                'status' => NotificationLog::STATUS_SKIPPED,
                'provider' => $preflight['provider'],
                'attempts' => $attempts,
                'errorMessage' => $preflight['error'],
                'externalMessageId' => null,
                'skippedReason' => $preflight['error'],
            ];
        }

        $attemptedAt = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $provider = $preflight['provider'];
        $externalMessageId = null;
        $error = null;
        $ok = false;

        try {
            $result = $this->messageDeliveryGateway->send($log, $this->buildHtmlBody($log));
            $ok = $result['ok'];
            $error = $result['error'];
            $provider = $result['provider'];
            $externalMessageId = $result['externalMessageId'];
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $attempts++;
        $status = $ok ? NotificationLog::STATUS_SENT : NotificationLog::STATUS_FAILED;

        $log->set('attempts', $attempts);
        $log->set('provider', $provider);
        $log->set('externalMessageId', $externalMessageId);
        $log->set('status', $status);
        $log->set('errorMessage', $ok ? null : (string) $error);
        $log->set('sentAt', $ok ? $attemptedAt : null);
        $log->set('payload', $this->appendDeliveryHistory($log, $attemptedAt, $attempts, $provider, $ok, $error));

        $this->entityManager->saveEntity($log);

        return [
            'id' => (string) $log->getId(),
            'status' => $status,
            'provider' => $provider,
            'attempts' => $attempts,
            'errorMessage' => $ok ? null : (string) $error,
            'externalMessageId' => $externalMessageId,
        ];
    }

    private function buildHtmlBody(NotificationLog $log): string
    {
        $text = (string) ($log->get('messageText') ?? '');

        return nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    /**
     * @return array<string, mixed>
     */
    private function appendDeliveryHistory(
        NotificationLog $log,
        string $attemptedAt,
        int $attempts,
        string $provider,
        bool $ok,
        ?string $error
    ): array {
        $payload = $log->get('payload');
        if (is_object($payload)) {
            $payload = (array) $payload;
        }

        if (!is_array($payload)) {
            $payload = [];
        }

        $history = $payload['deliveryHistory'] ?? [];
        if (!is_array($history)) {
            $history = [];
        }

        $history[] = [
            'attemptedAt' => $attemptedAt,
            'attempts' => $attempts,
            'provider' => $provider,
            'ok' => $ok,
            'error' => $error,
        ];

        $payload['deliveryHistory'] = $history;

        return $payload;
    }
}
