<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase36MessagingIntegrationTest extends TestCase
{
    private const MODULE_ROOT = __DIR__ . '/../src/files/custom/Espo/Modules/EspoDental';
    private const LOCALES = ['ru_RU', 'en_US', 'es_ES'];

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        $this->assertFileExists($path);
        $data = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($data, "Invalid JSON: $path");

        return $data;
    }

    public function testNotificationLogIsMessageOutboxAuditRecord(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/NotificationLog.json');

        $this->assertContains('whatsapp', $def['fields']['channel']['options']);
        $this->assertSame(['outbound', 'inbound'], $def['fields']['direction']['options']);
        $this->assertSame('outbound', $def['fields']['direction']['default']);
        foreach (['provider', 'externalMessageId', 'payload', 'attempts', 'status'] as $field) {
            $this->assertArrayHasKey($field, $def['fields']);
        }
        $this->assertArrayHasKey('external_message', $def['indexes']);
        $this->assertSame(['provider', 'externalMessageId'], $def['indexes']['external_message']['columns']);
    }

    public function testWhatsAppSettingsAreRegisteredAsSensitiveSystemAdapterSettings(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Settings.json');
        foreach ([
            'espoDentalWhatsAppEnabled',
            'espoDentalWhatsAppProvider',
            'espoDentalWhatsAppApiBase',
            'espoDentalWhatsAppAccessToken',
        ] as $field) {
            $this->assertArrayHasKey($field, $def['fields']);
            $this->assertSame('EspoDental', $def['fields'][$field]['tab']);
        }
        $this->assertSame('password', $def['fields']['espoDentalWhatsAppAccessToken']['type']);

        $appSettings = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/app/settings.json');
        $this->assertTrue($appSettings['params']['espoDentalWhatsAppAccessToken']['isSensitive']);
        $this->assertSame('system', $appSettings['params']['espoDentalWhatsAppProvider']['level']);

        $layout = (string) file_get_contents(self::MODULE_ROOT . '/Resources/layouts/Settings/espoDentalSettings.json');
        $this->assertStringContainsString('espoDentalWhatsAppEnabled', $layout);
        $this->assertStringContainsString('espoDentalWhatsAppAccessToken', $layout);
    }

    public function testMessagingGatewayRoutesReminderChannelsThroughAdapters(): void
    {
        $gateway = (string) file_get_contents(self::MODULE_ROOT . '/Tools/Messaging/MessageDeliveryGateway.php');
        $whatsApp = (string) file_get_contents(self::MODULE_ROOT . '/Tools/Messaging/WhatsAppSender.php');
        $service = (string) file_get_contents(self::MODULE_ROOT . '/Services/ReminderService.php');

        $this->assertStringContainsString('class MessageDeliveryGateway', $gateway);
        $this->assertStringContainsString('NotificationLog::CHANNEL_TELEGRAM', $gateway);
        $this->assertStringContainsString('NotificationLog::CHANNEL_WHATSAPP', $gateway);
        $this->assertStringContainsString('NotificationLog::CHANNEL_EMAIL', $gateway);
        $this->assertStringContainsString('unsupported_channel', $gateway);

        $this->assertStringContainsString('class WhatsAppSender', $whatsApp);
        $this->assertStringContainsString('espoDentalWhatsAppEnabled', $whatsApp);
        $this->assertStringContainsString('espoDentalWhatsAppAccessToken', $whatsApp);

        $this->assertStringContainsString('MessageDeliveryGateway $messageDeliveryGateway', $service);
        $this->assertStringContainsString('NotificationLog::CHANNEL_WHATSAPP', $service);
        $this->assertStringContainsString('DIRECTION_OUTBOUND', $service);
        $this->assertMatchesRegularExpression('/saveEntity\\(\\$log\\);\\s+\\$ok = false;/', $service);
        $this->assertStringNotContainsString('private function sendEmail', $service);
    }

    public function testLocalesExposeOutboxAndWhatsAppSettings(): void
    {
        foreach (self::LOCALES as $locale) {
            $notification = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/NotificationLog.json");
            $this->assertArrayHasKey('direction', $notification['fields']);
            $this->assertArrayHasKey('provider', $notification['fields']);
            $this->assertArrayHasKey('externalMessageId', $notification['fields']);
            $this->assertArrayHasKey('whatsapp', $notification['options']['channel']);
            $this->assertArrayHasKey('outbound', $notification['options']['direction']);

            $settings = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Settings.json");
            $this->assertArrayHasKey('espoDentalWhatsAppEnabled', $settings['fields']);
            $this->assertArrayHasKey('espoDentalWhatsAppAccessToken', $settings['fields']);
        }
    }

    public function testDocumentationRecordsFirstPhaseNineMessagingSlice(): void
    {
        $current = (string) file_get_contents(__DIR__ . '/../docs/current-state.md');
        $release = (string) file_get_contents(__DIR__ . '/../docs/release-notes.md');
        $architecture = (string) file_get_contents(__DIR__ . '/../docs/integration-architecture.md');

        $this->assertStringContainsString('message delivery gateway', $current);
        $this->assertStringContainsString('WhatsApp adapter', $release);
        $this->assertStringContainsString('MessageDeliveryGateway', $architecture);
        $this->assertStringContainsString('MCP', $architecture);
        $this->assertStringContainsString('LLM', $architecture);
    }
}
