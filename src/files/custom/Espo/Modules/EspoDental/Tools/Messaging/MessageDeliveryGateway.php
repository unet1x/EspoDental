<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Tools\Messaging;

use Espo\Core\Mail\Email;
use Espo\Core\Mail\EmailSender;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config;
use Espo\Modules\EspoDental\Entities\NotificationLog;
use Espo\Modules\EspoDental\Tools\TelegramSender;

class MessageDeliveryGateway
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly TelegramSender $telegramSender,
        private readonly WhatsAppSender $whatsAppSender,
        private readonly EmailSender $emailSender,
        private readonly Config $config
    ) {
    }

    public function isEnabled(string $channel): bool
    {
        return match ($channel) {
            NotificationLog::CHANNEL_TELEGRAM => $this->telegramSender->isEnabled(),
            NotificationLog::CHANNEL_WHATSAPP => $this->whatsAppSender->isEnabled(),
            NotificationLog::CHANNEL_EMAIL => true,
            default => false,
        };
    }

    public function providerFor(string $channel): string
    {
        return match ($channel) {
            NotificationLog::CHANNEL_TELEGRAM => 'telegram',
            NotificationLog::CHANNEL_WHATSAPP => $this->whatsAppSender->getProviderName(),
            NotificationLog::CHANNEL_EMAIL => 'smtp',
            default => $channel,
        };
    }

    /**
     * @return array{ok: bool, error: ?string, provider: string, externalMessageId: ?string}
     */
    public function send(NotificationLog $log, string $html = ''): array
    {
        $channel = (string) $log->get('channel');
        $recipient = (string) $log->get('recipient');
        $subject = (string) $log->get('subject');
        $text = (string) $log->get('messageText');
        $provider = $this->providerFor($channel);

        return match ($channel) {
            NotificationLog::CHANNEL_TELEGRAM => $this->sendTelegram($recipient, $text, $provider),
            NotificationLog::CHANNEL_WHATSAPP => $this->sendWhatsApp($recipient, $text, $log, $provider),
            NotificationLog::CHANNEL_EMAIL => $this->sendEmail($recipient, $subject, $html, $provider),
            default => [
                'ok' => false,
                'error' => 'unsupported_channel',
                'provider' => $provider,
                'externalMessageId' => null,
            ],
        };
    }

    /**
     * @return array{ok: bool, error: ?string, provider: string, externalMessageId: ?string}
     */
    private function sendTelegram(string $recipient, string $text, string $provider): array
    {
        $result = $this->telegramSender->send($recipient, $text);

        return [
            'ok' => $result['ok'],
            'error' => $result['error'],
            'provider' => $provider,
            'externalMessageId' => null,
        ];
    }

    /**
     * @return array{ok: bool, error: ?string, provider: string, externalMessageId: ?string}
     */
    private function sendWhatsApp(
        string $recipient,
        string $text,
        NotificationLog $log,
        string $provider
    ): array {
        $context = [
            'notificationLogId' => $log->getId(),
            'kind' => $log->get('kind'),
            'patientId' => $log->get('patientId'),
            'appointmentId' => $log->get('appointmentId'),
        ];

        $result = $this->whatsAppSender->send($recipient, $text, $context);

        return [
            'ok' => $result['ok'],
            'error' => $result['error'],
            'provider' => $provider,
            'externalMessageId' => $result['externalMessageId'],
        ];
    }

    /**
     * @return array{ok: bool, error: ?string, provider: string, externalMessageId: ?string}
     */
    private function sendEmail(string $address, string $subject, string $html, string $provider): array
    {
        try {
            $email = $this->entityManager->getNewEntity(Email::ENTITY_TYPE);
            $email->set('subject', $subject);
            $email->set('body', $html);
            $email->set('isHtml', true);
            $email->set('to', $address);
            $email->set('from', $this->config->get('outboundEmailFromAddress'));
            $this->emailSender->send($email);

            return ['ok' => true, 'error' => null, 'provider' => $provider, 'externalMessageId' => null];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
                'provider' => $provider,
                'externalMessageId' => null,
            ];
        }
    }
}
