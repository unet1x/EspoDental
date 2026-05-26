<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class SimpleStomDashboardActionCenterTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental/src';

    public function testBackendContractExists(): void
    {
        $service = $this->readFile(self::MODULE_ROOT . '/Services/DashboardActionCenterService.php');
        $controller = $this->readFile(self::MODULE_ROOT . '/Controllers/Dashboard.php');
        $routes = $this->readFile(self::MODULE_ROOT . '/Resources/routes.json');

        foreach (
            [
                'class DashboardActionCenterService',
                'getActionCenter',
                'getWaitingPatients',
                'getPendingActions',
                'getAssignedTasks',
                'getLowStockAlerts',
                'getWeeklyWorkload',
                'Appointment::STATUS_ARRIVED',
                'Appointment::STATUS_IN_PROGRESS',
                'AssistantActionProposal::STATUS_PENDING_REVIEW',
                'LowStockAlert::STATUS_OPEN',
                "getRDBRepository('Task')",
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $service);
        }

        $this->assertStringContainsString('class Dashboard', $controller);
        $this->assertStringContainsString('getActionActionCenter', $controller);
        $this->assertStringContainsString('DashboardActionCenterService', $controller);
        $this->assertStringContainsString('/EspoDental/Dashboard/actionCenter', $routes);
    }

    public function testDashletUsesSimpleStomUiKitAndEndpoint(): void
    {
        $metadata = $this->readFile(self::MODULE_ROOT . '/Resources/metadata/dashlets/DashboardActionCenter.json');
        $view = $this->readFile(self::CLIENT_ROOT . '/views/dashlets/dashboard-action-center.js');

        $this->assertStringContainsString('espo-dental:views/dashlets/dashboard-action-center', $metadata);
        $this->assertStringContainsString('displayRecords', $metadata);
        $this->assertStringContainsString('espo-dental:lib/simple-stom-ui', $view);
        $this->assertStringContainsString('EspoDental/Dashboard/actionCenter', $view);
        $this->assertStringContainsString('renderWaitingPatients', $view);
        $this->assertStringContainsString('renderPendingActions', $view);
        $this->assertStringContainsString('renderAssignedTasks', $view);
        $this->assertStringContainsString('renderWeeklyWorkload', $view);
        $this->assertStringContainsString('SimpleStomUi.workspace', $view);
    }

    public function testRoleDashboardTemplatesPlaceActionCenterFirst(): void
    {
        $seeder = $this->readFile(self::MODULE_ROOT . '/Tools/Installer/WorkspaceSeeder.php');

        foreach (
            [
                'ed-action-center',
                'ed-admin-action-center',
                'ed-doctor-action-center',
                'ed-assistant-action-center',
                'ed-manager-action-center',
                'ed-stock-action-center',
                'DashboardActionCenter',
                'actionCenterDashletOptions',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $seeder);
        }

        $this->assertStringContainsString("'DashboardActionCenter', 0, 0, 4, 4", $seeder);
    }

    public function testLocalesAndDocsTrackActionCenter(): void
    {
        foreach (['ru_RU', 'en_US', 'es_ES'] as $locale) {
            $global = $this->readFile(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Global.json");
            $this->assertStringContainsString('DashboardActionCenter', $global);
        }

        $readme = $this->readFile(self::ROOT . '/README.md');
        $plan = $this->readFile(self::ROOT . '/docs/simple-stom-migration-plan.md');
        $doc = $this->readFile(self::ROOT . '/docs/simple-stom-dashboard-action-center.md');

        $this->assertStringContainsString('docs/simple-stom-dashboard-action-center.md', $readme);
        $this->assertStringContainsString('| 3. Dashboard actions and tasks | Completed |', $plan);
        $this->assertStringContainsString('GET /EspoDental/Dashboard/actionCenter', $doc);
        $this->assertStringContainsString('native EspoCRM `Task`', $doc);
    }

    private function readFile(string $path): string
    {
        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        $this->assertNotFalse($contents);

        return $contents;
    }
}
