<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase60NotificationQueuePreflightTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental/src';

    public function testGatewayPreflightSharesProviderAcceptanceGateWithSend(): void
    {
        $gateway = $this->readFile(self::MODULE_ROOT . '/Tools/Messaging/MessageDeliveryGateway.php');

        foreach (
            [
                'public function preflight',
                'provider_acceptance_required',
                'unsupported_channel',
                'no_recipient',
                '$preflight = $this->preflight($log)',
                '$preflight[\'provider\']',
                'externalMessageId',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $gateway);
        }
    }

    public function testHealthcheckReturnsQueuedPreflightWithoutSending(): void
    {
        $service = $this->readFile(self::MODULE_ROOT . '/Services/IntegrationMcpService.php');

        foreach (
            [
                'MessageDeliveryGateway $messageDeliveryGateway',
                '$this->messageDeliveryGateway->preflight($log)',
                'queuedRows',
                'queuedReadyCount',
                'queuedBlockedCount',
                'deliveryGate',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $service);
        }

        $notificationHealth = $this->methodCode($service, 'buildNotificationHealth');
        $this->assertStringNotContainsString('->send(', $notificationHealth);
    }

    public function testIntegrationOpsCenterShowsQueuePreflight(): void
    {
        $view = $this->readFile(self::CLIENT_ROOT . '/views/dashlets/integration-ops-center.js');
        $ui = $this->readFile(self::CLIENT_ROOT . '/lib/simple-stom-ui.js');

        foreach (
            [
                'renderQueuedNotifications',
                'notifications.queuedRows',
                'deliveryGate',
                'Preflight очереди',
                'queuedBlockedCount',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $view);
        }

        foreach (['provider_acceptance_required', 'unsupported_channel', 'no_recipient'] as $status) {
            $this->assertStringContainsString($status, $ui);
        }
    }

    public function testDocsTrackQueuePreflight(): void
    {
        foreach (
            [
                self::ROOT . '/docs/espo-dental-product-development-plan.md',
                self::ROOT . '/docs/current-state.md',
                self::ROOT . '/docs/integration-architecture.md',
                self::ROOT . '/docs/release-notes.md',
                self::ROOT . '/docs/simple-stom-demo-runbook.md',
                self::ROOT . '/docs/simple-stom-migration-plan.md',
            ] as $path
        ) {
            $doc = $this->readFile($path);

            $this->assertStringContainsString('queue preflight', $doc);
            $this->assertStringContainsString('queuedBlockedCount', $doc);
        }
    }

    private function methodCode(string $code, string $method): string
    {
        $start = strpos($code, 'private function ' . $method);
        $this->assertIsInt($start);

        $next = strpos($code, 'private function ', $start + 1);
        $this->assertIsInt($next);

        return substr($code, $start, $next - $start);
    }

    private function readFile(string $path): string
    {
        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        $this->assertNotFalse($contents);

        return $contents;
    }
}
