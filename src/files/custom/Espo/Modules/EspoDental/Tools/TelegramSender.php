<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Tools;

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Log;

class TelegramSender
{
    public function __construct(
        private readonly Config $config,
        private readonly Log $log
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) $this->config->get('espoDentalTelegramEnabled', false)
            && (string) $this->config->get('espoDentalTelegramBotToken', '') !== '';
    }

    /**
     * @return array{ok: bool, error: ?string}
     */
    public function send(string $chatId, string $text): array
    {
        if (!$this->isEnabled()) {
            return ['ok' => false, 'error' => 'telegram_disabled'];
        }
        if ($chatId === '') {
            return ['ok' => false, 'error' => 'no_chat_id'];
        }

        $token = (string) $this->config->get('espoDentalTelegramBotToken', '');
        $base = rtrim((string) $this->config->get('espoDentalTelegramApiBase', 'https://api.telegram.org'), '/');
        $url = $base . '/bot' . $token . '/sendMessage';

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'curl_init_failed'];
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            $this->log->warning('EspoDental Telegram send failed: ' . $err);
            return ['ok' => false, 'error' => 'curl_error: ' . $err];
        }
        if ($code < 200 || $code >= 300) {
            $this->log->warning('EspoDental Telegram HTTP ' . $code . ': ' . (string) $body);
            return ['ok' => false, 'error' => 'http_' . $code];
        }

        return ['ok' => true, 'error' => null];
    }
}
