<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use Espo\Core\Mail\Email;
use Espo\Core\Mail\EmailSender;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config;
use Espo\Modules\EspoDental\Entities\Appointment;
use Espo\Modules\EspoDental\Entities\NotificationLog;
use Espo\Modules\EspoDental\Entities\Patient;
use Espo\Modules\EspoDental\Tools\ReminderTemplate;
use Espo\Modules\EspoDental\Tools\TelegramSender;
use Espo\ORM\Entity;

class ReminderService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ReminderTemplate $template,
        private readonly TelegramSender $telegramSender,
        private readonly EmailSender $emailSender,
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

        $parent = $this->loadParent($appointment);
        if (!$parent) {
            return $stats;
        }
        if ($parent instanceof Patient && !(bool) $parent->get('remindersEnabled', true)) {
            $stats['skipped']++;
            return $stats;
        }

        $preferred = (string) $parent->get('preferredChannel');
        $channels = $this->resolveChannels($preferred, $parent);

        $language = (string) ($this->config->get('language') ?: 'ru_RU');
        $tpl = $this->template->build($appointment, $parent, $language);

        foreach ($channels as $channel => $recipient) {
            if ($this->alreadyLogged($appointment, $channel, $hoursBefore)) {
                $stats['skipped']++;
                continue;
            }
            $log = $this->createLog($appointment, $parent, $channel, $recipient, $tpl);
            $ok = false;
            $error = null;
            try {
                if ($channel === NotificationLog::CHANNEL_TELEGRAM) {
                    $r = $this->telegramSender->send($recipient, $tpl['text']);
                    $ok = $r['ok'];
                    $error = $r['error'];
                } elseif ($channel === NotificationLog::CHANNEL_EMAIL) {
                    $ok = $this->sendEmail($recipient, $tpl['subject'], $tpl['html']);
                }
            } catch (\Throwable $e) {
                $ok = false;
                $error = $e->getMessage();
            }
            $log->set('attempts', 1);
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

    /**
     * @return array<string, string> channel => recipient
     */
    private function resolveChannels(string $preferred, Entity $parent): array
    {
        $email = (string) $parent->get('emailAddress');
        $chatId = (string) $parent->get('telegramChatId');

        if ($preferred === 'telegram' && $chatId !== '') {
            return [NotificationLog::CHANNEL_TELEGRAM => $chatId];
        }
        if ($preferred === 'email' && $email !== '') {
            return [NotificationLog::CHANNEL_EMAIL => $email];
        }

        $channels = [];
        if ($chatId !== '' && $this->telegramSender->isEnabled()) {
            $channels[NotificationLog::CHANNEL_TELEGRAM] = $chatId;
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
        array $tpl
    ): NotificationLog {
        /** @var NotificationLog $log */
        $log = $this->entityManager->getNewEntity(NotificationLog::ENTITY_TYPE);
        $log->set('name', $tpl['subject']);
        $log->set('patientId', $parent instanceof Patient ? $parent->getId() : null);
        $log->set('appointmentId', $appointment->getId());
        $log->set('channel', $channel);
        $log->set('kind', NotificationLog::KIND_REMINDER);
        $log->set('recipient', $recipient);
        $log->set('subject', $tpl['subject']);
        $log->set('messageText', $tpl['text']);
        $log->set('status', NotificationLog::STATUS_QUEUED);
        return $log;
    }

    private function sendEmail(string $address, string $subject, string $html): bool
    {
        try {
            $email = $this->entityManager->getNewEntity(Email::ENTITY_TYPE);
            $email->set('subject', $subject);
            $email->set('body', $html);
            $email->set('isHtml', true);
            $email->set('to', $address);
            $email->set('from', $this->config->get('outboundEmailFromAddress'));
            $this->emailSender->send($email);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
