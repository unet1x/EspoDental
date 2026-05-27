<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase54IntegrationOpsCenterTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental/src';

    public function testIntegrationHealthcheckEndpointAuditsToolsNotificationsAndProposals(): void
    {
        $routes = $this->readJson(self::MODULE_ROOT . '/Resources/routes.json');
        $paths = array_column($routes, 'route');
        $controller = $this->readFile(self::MODULE_ROOT . '/Controllers/Integration.php');
        $service = $this->readFile(self::MODULE_ROOT . '/Services/IntegrationMcpService.php');

        $this->assertContains('/EspoDental/Integration/healthcheck', $paths);
        $this->assertStringContainsString('getActionHealthcheck', $controller);
        $this->assertStringContainsString("checkScope(\$scope, 'read')", $controller);
        $this->assertStringContainsString("'NotificationLog'", $controller);
        $this->assertStringContainsString("'AssistantActionProposal'", $controller);
        $this->assertStringContainsString("'IntegrationSettings'", $controller);
        $this->assertStringContainsString('getHealthcheck', $service);

        foreach (
            [
                'buildToolAudit',
                'buildIntegrationReadiness',
                'buildNotificationHealth',
                'buildProposalHealth',
                'retryCandidateCount',
                'integration.healthcheck',
                'directMutationToolCount',
                'blockedDirectMutations',
                'needsSecretCount',
                'highRiskPendingCount',
                'NotificationLog::STATUS_FAILED',
                'AssistantActionProposal::STATUS_PENDING_REVIEW',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $service);
        }
    }

    public function testIntegrationOpsCenterDashletIsOnManagerDashboard(): void
    {
        $dashlet = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/dashlets/IntegrationOpsCenter.json');
        $view = $this->readFile(self::CLIENT_ROOT . '/views/dashlets/integration-ops-center.js');
        $workspaceSeeder = $this->readFile(self::MODULE_ROOT . '/Tools/Installer/WorkspaceSeeder.php');
        $managerLayout = $this->methodCode($workspaceSeeder, 'managerDashboardLayout');
        $managerOptions = $this->methodCode($workspaceSeeder, 'managerDashletsOptions');

        $this->assertSame('espo-dental:views/dashlets/integration-ops-center', $dashlet['view']);
        $this->assertSame('IntegrationSettings', $dashlet['aclScope']);
        $this->assertStringContainsString("name: 'IntegrationOpsCenter'", $view);
        $this->assertStringContainsString('EspoDental/Integration/healthcheck', $view);
        $this->assertStringContainsString('failedRows', $view);
        $this->assertStringContainsString('retryCandidateCount', $view);
        $this->assertStringContainsString('IntegrationOpsCenter', $managerLayout);
        $this->assertStringContainsString('ed-manager-integration-ops', $managerLayout);
        $this->assertStringContainsString('Контроль интеграций', $managerOptions);
        $this->assertStringContainsString(
            "'ed-manager-integration-ops', 'IntegrationOpsCenter', 0, 12, 4, 4",
            $managerLayout
        );
    }

    public function testLocalesAndDocsTrackIntegrationOpsCenter(): void
    {
        foreach (['en_US', 'ru_RU', 'es_ES'] as $locale) {
            $global = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Global.json");

            $this->assertArrayHasKey('IntegrationOpsCenter', $global['dashlets']);
        }

        $ui = $this->readFile(self::CLIENT_ROOT . '/lib/simple-stom-ui.js');
        $this->assertStringContainsString('pending_review', $ui);
        $this->assertStringContainsString('needs_secret', $ui);
        $this->assertStringContainsString('failed', $ui);

        foreach (
            [
                self::ROOT . '/docs/current-state.md',
                self::ROOT . '/docs/release-notes.md',
                self::ROOT . '/docs/integration-architecture.md',
                self::ROOT . '/docs/mcp-server-design.md',
                self::ROOT . '/docs/espo-dental-product-development-plan.md',
            ] as $path
        ) {
            $doc = $this->readFile($path);

            $this->assertStringContainsString('Integration/healthcheck', $doc);
            $this->assertStringContainsString('IntegrationOpsCenter', $doc);
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
