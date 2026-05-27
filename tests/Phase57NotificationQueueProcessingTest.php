<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase57NotificationQueueProcessingTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental/src';

    public function testProcessQueueRouteControllerAndServiceUseDeliveryBoundary(): void
    {
        $routes = $this->readJson(self::MODULE_ROOT . '/Resources/routes.json');
        $routeMap = [];
        foreach ($routes as $route) {
            $routeMap[$route['route']] = $route;
        }

        $this->assertArrayHasKey('/EspoDental/NotificationLog/processQueue', $routeMap);
        $this->assertSame('post', $routeMap['/EspoDental/NotificationLog/processQueue']['method']);
        $this->assertSame(
            'NotificationLog',
            $routeMap['/EspoDental/NotificationLog/processQueue']['params']['controller']
        );
        $this->assertSame('processQueue', $routeMap['/EspoDental/NotificationLog/processQueue']['params']['action']);

        $controller = $this->readFile(self::MODULE_ROOT . '/Controllers/NotificationLog.php');
        $service = $this->readFile(self::MODULE_ROOT . '/Services/NotificationDeliveryService.php');

        $this->assertStringContainsString('postActionProcessQueue', $controller);
        $this->assertStringContainsString('NotificationDeliveryService::class', $controller);
        $this->assertStringContainsString('processQueued', $service);
        $this->assertStringContainsString('processOne', $service);
        $this->assertStringContainsString('MessageDeliveryGateway $messageDeliveryGateway', $service);
        $this->assertStringContainsString('$this->messageDeliveryGateway->send', $service);
        $this->assertStringContainsString('STATUS_QUEUED', $service);
        $this->assertStringContainsString('STATUS_SENT', $service);
        $this->assertStringContainsString('STATUS_FAILED', $service);
        $this->assertStringContainsString('MAX_ATTEMPTS', $service);
        $this->assertStringContainsString('deliveryHistory', $service);
        $this->assertStringContainsString('buildHtmlBody', $service);
        $this->assertStringContainsString('htmlspecialchars', $service);
    }

    public function testRecordButtonHandlerAndLocalesExposeProcessQueued(): void
    {
        $clientDefs = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/clientDefs/NotificationLog.json');
        $handler = $this->readFile(self::CLIENT_ROOT . '/handlers/notification-log/process-queued.js');

        $buttons = $clientDefs['menu']['detail']['buttons'];
        $this->assertSame('processQueued', $buttons[1]['name']);
        $this->assertSame('edit', $buttons[1]['acl']);
        $this->assertSame('espo-dental:handlers/notification-log/process-queued', $buttons[1]['handler']);
        $this->assertSame('actionProcessQueued', $buttons[1]['actionFunction']);

        foreach (
            [
                'actionProcessQueued',
                "model.get('status') !== 'queued'",
                'EspoDental/NotificationLog/processQueue',
                'Process queued notification confirmation',
                'Queued notification processed',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $handler);
        }

        foreach (['ru_RU', 'en_US', 'es_ES'] as $locale) {
            $labels = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/NotificationLog.json");

            $this->assertArrayHasKey('Process Queued', $labels['labels']);
            $this->assertArrayHasKey('Only queued notifications can be processed', $labels['messages']);
            $this->assertArrayHasKey('Queued notification processed', $labels['messages']);
            $this->assertArrayHasKey('Process queued failed', $labels['messages']);
        }
    }

    public function testIntegrationOpsCenterCanProcessQueueExplicitly(): void
    {
        $view = $this->readFile(self::CLIENT_ROOT . '/views/dashlets/integration-ops-center.js');

        $this->assertStringContainsString('processNotificationQueue', $view);
        $this->assertStringContainsString('notificationSummary.queuedCount', $view);
        $this->assertStringContainsString('renderQueueControls', $view);
        $this->assertStringContainsString('EspoDental/NotificationLog/processQueue', $view);
        $this->assertStringContainsString('delivery gateway', $view);
    }

    public function testDocsTrackQueuedDeliveryPass(): void
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

            $this->assertStringContainsString('NotificationDeliveryService', $doc);
            $this->assertStringContainsString('processQueue', $doc);
        }
    }

    private function readFile(string $path): string
    {
        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        $this->assertNotFalse($contents);

        return $contents;
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        $this->assertFileExists($path);

        $contents = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($contents, "Invalid JSON: {$path}");

        return $contents;
    }
}
