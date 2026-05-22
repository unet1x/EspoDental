<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Tools\Messaging;

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Log;

class WhatsAppSender
{
    public function __construct(
        private readonly Config $config,
        private readonly Log $log
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) $this->config->get('espoDentalWhatsAppEnabled', false)
            && (string) $this->config->get('espoDentalWhatsAppApiBase', '') !== ''
            && (string) $this->config->get('espoDentalWhatsAppAccessToken', '') !== '';
    }

    public function getProviderName(): string
    {
        $provider = trim((string) $this->config->get('espoDentalWhatsAppProvider', 'generic'));

        return $provider !== '' ? $provider : 'generic';
    }

    /**
     * @param array<string, mixed> $context
     * @return array{ok: bool, error: ?string, externalMessageId: ?string}
     */
    public function send(string $recipient, string $text, array $context = []): array
    {
        if (!$this->isEnabled()) {
            return ['ok' => false, 'error' => 'whatsapp_disabled', 'externalMessageId' => null];
        }
        if ($recipient === '') {
            return ['ok' => false, 'error' => 'no_whatsapp_recipient', 'externalMessageId' => null];
        }

        $base = rtrim((string) $this->config->get('espoDentalWhatsAppApiBase', ''), '/');
        $token = (string) $this->config->get('espoDentalWhatsAppAccessToken', '');
        $payload = (new WhatsAppMessagePayloadFactory())->buildTextPayload(
            $this->getProviderName(),
            $recipient,
            $text,
            $context
        );

        $ch = curl_init($base);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'curl_init_failed', 'externalMessageId' => null];
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POST, true);
        $encodedPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedPayload !== false ? $encodedPayload : '{}');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            $this->log->warning('EspoDental WhatsApp send failed: ' . $err);

            return ['ok' => false, 'error' => 'curl_error: ' . $err, 'externalMessageId' => null];
        }
        if ($code < 200 || $code >= 300) {
            $this->log->warning('EspoDental WhatsApp HTTP ' . $code . ': ' . (string) $body);

            return ['ok' => false, 'error' => 'http_' . $code, 'externalMessageId' => null];
        }

        $decoded = json_decode((string) $body, true);
        $externalMessageId = is_array($decoded) ? $this->extractMessageId($decoded) : null;

        return ['ok' => true, 'error' => null, 'externalMessageId' => $externalMessageId];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractMessageId(array $payload): ?string
    {
        if (isset($payload['id']) && is_scalar($payload['id'])) {
            return (string) $payload['id'];
        }

        $messages = $payload['messages'] ?? null;
        if (is_array($messages) && isset($messages[0]) && is_array($messages[0])) {
            $id = $messages[0]['id'] ?? null;
            if (is_scalar($id)) {
                return (string) $id;
            }
        }

        return null;
    }
}
