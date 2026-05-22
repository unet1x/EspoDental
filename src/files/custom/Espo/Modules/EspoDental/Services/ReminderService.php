<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config;
use Espo\Modules\EspoDental\Entities\Appointment;
use Espo\Modules\EspoDental\Entities\NotificationLog;
use Espo\Modules\EspoDental\Entities\Patient;
use Espo\Modules\EspoDental\Tools\Messaging\MessageDeliveryGateway;
use Espo\Modules\EspoDental\Tools\ReminderTemplate;
use Espo\ORM\Entity;

class ReminderService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ReminderTemplate $template,
        private readonly MessageDeliveryGateway $messageDeliveryGateway,
        private readonly Config $config
    ) {
    }

    /**
     * @return array{sent: int, failed: int, skipped: int, processed: int}
     */
    public function sendDueReminders(): array
    {
        $now = new DateTimeImmutable();
        $window = max(5, (int) $this->config->get('espoDentalReminderWindowMinutes', 30));
        $firstHours = max(1, (int) $this->config->get('espoDentalReminderHoursBefore', 24));
        $secondHours = (int) $this->config->get('espoDentalReminderSecondHoursBefore', 2);

        $stats = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'processed' => 0];
        $offsets = [$firstHours];
        if ($secondHours > 0 && $secondHours !== $firstHours) {
            $offsets[] = $secondHours;
        }

        foreach ($offsets as $hoursBefore) {
            $target = $now->modify('+' . $hoursBefore . ' hours');
            $from = $target->modify('-' . ($window / 2) . ' minutes')->format('Y-m-d H:i:s');
            $to = $target->modify('+' . ($window / 2) . ' minutes')->format('Y-m-d H:i:s');

            /** @var iterable<Appointment> $appointments */
            $appointments = $this->entityManager
                ->getRDBRepository(Appointment::ENTITY_TYPE)
                ->where([
                    'status' => Appointment::BLOCKING_STATUSES,
                    'dateStart>=' => $from,
                    'dateStart<' => $to,
                    'deleted' => false,
                ])
                ->find();

            foreach ($appointments as $appointment) {
                $stats['processed']++;
                $result = $this->sendForAppointment($appointment, $hoursBefore);
                $stats['sent'] += $result['sent'];
                $stats['failed'] += $result['failed'];
                $stats['skipped'] += $result['skipped'];
            }
        }

        return $stats;
    }

    /**
     * @return array{sent: int, failed: int, skipped: int}
     */
    public function sendForAppointment(Appointment $appointment, int $hoursBefore): array
    {
        $stats = ['sent' => 0, 'failed' => 0, 'skipped' => 0];

        $appointmentParent = $this->loadParent($appointment);
        if (!$appointmentParent) {
            return $stats;
        }

        $recipientSource = $this->resolveRecipientSource($appointmentParent);
        if ($recipientSource instanceof Patient && !(bool) $recipientSource->get('remindersEnabled', true)) {
            $stats['skipped']++;
            return $stats;
        }

        $preferred = (string) $recipientSource->get('preferredChannel');
        $channels = $this->resolveChannels($preferred, $recipientSource);

        $language = (string) ($this->config->get('language') ?: 'ru_RU');
        $tpl = $this->template->build($appointment, $appointmentParent, $language);

        foreach ($channels as $channel => $recipient) {
            if ($this->alreadyLogged($appointment, $channel, $hoursBefore)) {
                $stats['skipped']++;
                continue;
            }
            $log = $this->createLog($appointment, $appointmentParent, $channel, $recipient, $tpl, $hoursBefore);
            $this->entityManager->saveEntity($log);

            $ok = false;
            $error = null;
            $externalMessageId = null;
            $provider = $this->messageDeliveryGateway->providerFor($channel);
            try {
                $result = $this->messageDeliveryGateway->send($log, $tpl['html']);
                $ok = $result['ok'];
                $error = $result['error'];
                $provider = $result['provider'];
                $externalMessageId = $result['externalMessageId'];
            } catch (\Throwable $e) {
                $ok = false;
                $error = $e->getMessage();
            }
            $log->set('attempts', 1);
            $log->set('provider', $provider);
            $log->set('externalMessageId', $externalMessageId);
            $log->set('status', $ok ? NotificationLog::STATUS_SENT : NotificationLog::STATUS_FAILED);
            if ($ok) {
                $log->set('sentAt', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
                $stats['sent']++;
            } else {
                $log->set('errorMessage', (string) $error);
                $stats['failed']++;
            }
            $this->entityManager->saveEntity($log);
        }

        return $stats;
    }

    private function loadParent(Appointment $appointment): ?Entity
    {
        $type = $appointment->getParentType();
        $id = $appointment->getParentId();
        if (!$type || !$id) {
            return null;
        }
        return $this->entityManager->getEntityById($type, $id);
    }

    private function resolveRecipientSource(Entity $appointmentParent): Entity
    {
        if (!$appointmentParent instanceof Patient || !$appointmentParent->isChild()) {
            return $appointmentParent;
        }

        $parentPatientId = $appointmentParent->getParentPatientId();
        if (!$parentPatientId) {
            return $appointmentParent;
        }

        $linkedParent = $this->entityManager->getEntityById(Patient::ENTITY_TYPE, $parentPatientId);
        return $linkedParent instanceof Patient ? $linkedParent : $appointmentParent;
    }

    /**
     * @return array<string, string> channel => recipient
     */
    private function resolveChannels(string $preferred, Entity $parent): array
    {
        $email = (string) $parent->get('emailAddress');
        $chatId = (string) $parent->get('telegramChatId');
        $whatsApp = (string) ($parent->get('whatsapp') ?: $parent->get('phone'));

        if ($preferred === 'telegram' && $chatId !== '') {
            return [NotificationLog::CHANNEL_TELEGRAM => $chatId];
        }
        if ($preferred === 'whatsapp' && $whatsApp !== '') {
            return [NotificationLog::CHANNEL_WHATSAPP => $whatsApp];
        }
        if ($preferred === 'email' && $email !== '') {
            return [NotificationLog::CHANNEL_EMAIL => $email];
        }

        $channels = [];
        if ($chatId !== '' && $this->messageDeliveryGateway->isEnabled(NotificationLog::CHANNEL_TELEGRAM)) {
            $channels[NotificationLog::CHANNEL_TELEGRAM] = $chatId;
        }
        if ($whatsApp !== '' && $this->messageDeliveryGateway->isEnabled(NotificationLog::CHANNEL_WHATSAPP)) {
            $channels[NotificationLog::CHANNEL_WHATSAPP] = $whatsApp;
        }
        if ($email !== '') {
            $channels[NotificationLog::CHANNEL_EMAIL] = $email;
        }
        return $channels;
    }

    private function alreadyLogged(Appointment $appointment, string $channel, int $hoursBefore): bool
    {
        $existing = $this->entityManager
            ->getRDBRepository(NotificationLog::ENTITY_TYPE)
            ->where([
                'appointmentId' => $appointment->getId(),
                'channel' => $channel,
                'kind' => NotificationLog::KIND_REMINDER,
                'subject*' => '%(' . $hoursBefore . 'h)%',
            ])
            ->findOne();
        return $existing !== null;
    }

    /**
     * @param array{subject: string, text: string, html: string} $tpl
     */
    private function createLog(
        Appointment $appointment,
        Entity $parent,
        string $channel,
        string $recipient,
        array $tpl,
        int $hoursBefore
    ): NotificationLog {
        /** @var NotificationLog $log */
        $log = $this->entityManager->getNewEntity(NotificationLog::ENTITY_TYPE);
        $log->set('name', $tpl['subject']);
        $log->set('patientId', $parent instanceof Patient ? $parent->getId() : null);
        $log->set('appointmentId', $appointment->getId());
        $log->set('channel', $channel);
        $log->set('direction', NotificationLog::DIRECTION_OUTBOUND);
        $log->set('provider', $this->messageDeliveryGateway->providerFor($channel));
        $log->set('kind', NotificationLog::KIND_REMINDER);
        $log->set('recipient', $recipient);
        $log->set('subject', $tpl['subject']);
        $log->set('messageText', $tpl['text']);
        $log->set('status', NotificationLog::STATUS_QUEUED);
        $log->set('payload', [
            'template' => NotificationLog::KIND_REMINDER,
            'source' => 'reminder-service',
            'hoursBefore' => $hoursBefore,
        ]);
        return $log;
    }
}
