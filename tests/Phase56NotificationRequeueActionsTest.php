<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase56NotificationRequeueActionsTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental/src';

    public function testRequeueRouteAndControllerKeepDeliveryBoundaryPassive(): void
    {
        $routes = $this->readJson(self::MODULE_ROOT . '/Resources/routes.json');
        $routeMap = [];
        foreach ($routes as $route) {
            $routeMap[$route['route']] = $route;
        }

        $this->assertArrayHasKey('/EspoDental/NotificationLog/requeue', $routeMap);
        $this->assertSame('post', $routeMap['/EspoDental/NotificationLog/requeue']['method']);
        $this->assertSame('NotificationLog', $routeMap['/EspoDental/NotificationLog/requeue']['params']['controller']);
        $this->assertSame('requeue', $routeMap['/EspoDental/NotificationLog/requeue']['params']['action']);

        $controller = $this->readFile(self::MODULE_ROOT . '/Controllers/NotificationLog.php');

        foreach (
            [
                'postActionRequeue',
                "checkScope(NotificationLogEntity::ENTITY_TYPE, 'edit')",
                'STATUS_FAILED',
                'STATUS_QUEUED',
                'MAX_REQUEUE_ATTEMPTS',
                'Only failed notifications can be requeued',
                'Notification retry limit reached',
                'is_object($payload)',
                'requeueHistory',
                'requestedById',
                'previousErrorMessage',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $controller);
        }

        $this->assertStringNotContainsString('MessageDeliveryGateway', $controller);
        $this->assertStringNotContainsString('->send(', $controller);
    }

    public function testRecordButtonHandlerAndLocalesExposeRequeue(): void
    {
        $clientDefs = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/clientDefs/NotificationLog.json');
        $handler = $this->readFile(self::CLIENT_ROOT . '/handlers/notification-log/requeue.js');

        $button = $clientDefs['menu']['detail']['buttons'][0];
        $this->assertSame('requeue', $button['name']);
        $this->assertSame('edit', $button['acl']);
        $this->assertSame('espo-dental:handlers/notification-log/requeue', $button['handler']);
        $this->assertSame('actionRequeue', $button['actionFunction']);

        foreach (
            [
                'actionRequeue',
                "model.get('status') !== 'failed'",
                'EspoDental/NotificationLog/requeue',
                'note: note ||',
                'Notification requeued',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $handler);
        }

        foreach (['ru_RU', 'en_US', 'es_ES'] as $locale) {
            $labels = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/NotificationLog.json");

            $this->assertArrayHasKey('Requeue', $labels['labels']);
            $this->assertArrayHasKey('Requeue notification', $labels['labels']);
            $this->assertArrayHasKey('Only failed notifications can be requeued', $labels['messages']);
            $this->assertArrayHasKey('Notification requeued', $labels['messages']);
        }
    }

    public function testIntegrationOpsCenterCanRequeueRetryCandidates(): void
    {
        $view = $this->readFile(self::CLIENT_ROOT . '/views/dashlets/integration-ops-center.js');

        $this->assertStringContainsString('espo-dental:utils/dialogs', $view);
        $this->assertStringContainsString('requeueNotification', $view);
        $this->assertStringContainsString('EspoDental/NotificationLog/requeue', $view);
        $this->assertStringContainsString('row.retryCandidate', $view);
        $this->assertStringContainsString("data-action': 'requeueNotification'", $view);
        $this->assertStringContainsString('this.fetchData();', $view);
    }

    public function testDocsTrackNotificationRequeueWorkflow(): void
    {
        foreach (
            [
                self::ROOT . '/docs/espo-dental-product-development-plan.md',
                self::ROOT . '/docs/current-state.md',
                self::ROOT . '/docs/integration-architecture.md',
                self::ROOT . '/docs/release-notes.md',
                self::ROOT . '/docs/simple-stom-demo-runbook.md',
                self::ROOT . '/docs/mcp-server-design.md',
            ] as $path
        ) {
            $doc = $this->readFile($path);

            $this->assertStringContainsString('NotificationLog', $doc);
            $this->assertStringContainsString('requeue', $doc);
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
