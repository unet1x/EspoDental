<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Tools\Messaging;

final class WhatsAppMessagePayloadFactory
{
    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function buildTextPayload(string $provider, string $recipient, string $text, array $context = []): array
    {
        if ($this->isWhatsAppCloudProvider($provider)) {
            return [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $recipient,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $text,
                ],
            ];
        }

        return [
            'to' => $recipient,
            'type' => 'text',
            'text' => ['body' => $text],
            'context' => $context,
        ];
    }

    public function isWhatsAppCloudProvider(string $provider): bool
    {
        return in_array($this->normalizeProvider($provider), [
            'whatsapp-cloud',
            'meta-cloud',
            'facebook-cloud',
        ], true);
    }

    private function normalizeProvider(string $provider): string
    {
        return str_replace(['_', ' '], '-', strtolower(trim($provider)));
    }
}
