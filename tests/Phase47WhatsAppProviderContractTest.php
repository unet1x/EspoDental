<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use Espo\Modules\EspoDental\Tools\Messaging\WhatsAppMessagePayloadFactory;
use PHPUnit\Framework\TestCase;

final class Phase47WhatsAppProviderContractTest extends TestCase
{
    private const MODULE_ROOT = __DIR__ . '/../src/files/custom/Espo/Modules/EspoDental';

    public function testGenericProviderKeepsProxyPayloadWithAuditContext(): void
    {
        $factory = new WhatsAppMessagePayloadFactory();

        $payload = $factory->buildTextPayload('generic', '+34600111222', 'Reminder text', [
            'notificationLogId' => 'abc123',
            'appointmentId' => 'apt123',
        ]);

        $this->assertSame('+34600111222', $payload['to']);
        $this->assertSame('text', $payload['type']);
        $this->assertSame(['body' => 'Reminder text'], $payload['text']);
        $this->assertSame('abc123', $payload['context']['notificationLogId']);
        $this->assertArrayNotHasKey('messaging_product', $payload);
    }

    public function testWhatsAppCloudProviderBuildsMetaCloudTextPayload(): void
    {
        $factory = new WhatsAppMessagePayloadFactory();

        $payload = $factory->buildTextPayload('whatsapp-cloud', '34600111222', 'Reminder text', [
            'notificationLogId' => 'abc123',
        ]);

        $this->assertSame('whatsapp', $payload['messaging_product']);
        $this->assertSame('individual', $payload['recipient_type']);
        $this->assertSame('34600111222', $payload['to']);
        $this->assertSame('text', $payload['type']);
        $this->assertSame(['preview_url' => false, 'body' => 'Reminder text'], $payload['text']);
        $this->assertArrayNotHasKey('context', $payload);
    }

    public function testWhatsAppCloudAliasesAreAccepted(): void
    {
        $factory = new WhatsAppMessagePayloadFactory();

        $this->assertTrue($factory->isWhatsAppCloudProvider('whatsapp_cloud'));
        $this->assertTrue($factory->isWhatsAppCloudProvider('Meta Cloud'));
        $this->assertTrue($factory->isWhatsAppCloudProvider('facebook-cloud'));
        $this->assertFalse($factory->isWhatsAppCloudProvider('generic'));
    }

    public function testSenderUsesProviderAwarePayloadFactoryAndStillExtractsCloudMessageIds(): void
    {
        $sender = (string) file_get_contents(self::MODULE_ROOT . '/Tools/Messaging/WhatsAppSender.php');

        $this->assertStringContainsString('new WhatsAppMessagePayloadFactory()', $sender);
        $this->assertStringContainsString('$this->getProviderName()', $sender);
        $this->assertStringContainsString("\$messages = \$payload['messages'] ?? null;", $sender);
    }

    public function testDocumentationRecordsWhatsAppCloudProviderContract(): void
    {
        $architecture = (string) file_get_contents(__DIR__ . '/../docs/integration-architecture.md');
        $adminGuide = (string) file_get_contents(__DIR__ . '/../docs/admin-guide.md');
        $current = (string) file_get_contents(__DIR__ . '/../docs/current-state.md');

        $this->assertStringContainsString('whatsapp-cloud', $architecture);
        $this->assertStringContainsString('/{phone-number-id}/messages', $adminGuide);
        $this->assertStringContainsString('provider-specific WhatsApp payloads', $current);
    }
}
