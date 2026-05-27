<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase51ManagementSnapshotTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental/src';

    public function testManagementSnapshotEndpointAggregatesManagerControlData(): void
    {
        $service = $this->readFile(self::MODULE_ROOT . '/Services/ReportService.php');
        $controller = $this->readFile(self::MODULE_ROOT . '/Controllers/Report.php');
        $routes = $this->readJson(self::MODULE_ROOT . '/Resources/routes.json');
        $paths = array_column($routes, 'route');

        $this->assertContains('/EspoDental/Report/managementSnapshot', $paths);
        $this->assertStringContainsString('getActionManagementSnapshot', $controller);
        $this->assertStringContainsString('getManagementSnapshot', $service);

        foreach (
            [
                'getInvoiceRisk',
                'getPayrollSnapshot',
                'getMaterialCost',
                'SalaryEntry::ENTITY_TYPE',
                'StockMovement::TYPE_CONSUMPTION',
                'StockMovement::TYPE_WRITEOFF',
                'getDoctorProductivity',
                'getCabinetUtilization',
                'getNoShowCancellations',
                'grossAfterKnownCosts',
                'openInvoiceBalance',
                'payrollAccrued',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $service);
        }
    }

    public function testManagementSnapshotDashletExistsAndUsesReportEndpoint(): void
    {
        $dashlet = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/dashlets/ManagementSnapshot.json');
        $view = $this->readFile(self::CLIENT_ROOT . '/views/dashlets/management-snapshot.js');
        $ui = $this->readFile(self::CLIENT_ROOT . '/lib/simple-stom-ui.js');

        $this->assertSame('espo-dental:views/dashlets/management-snapshot', $dashlet['view']);
        $this->assertSame('ReportDefinition', $dashlet['aclScope']);
        $this->assertStringContainsString("name: 'ManagementSnapshot'", $view);
        $this->assertStringContainsString('EspoDental/Report/managementSnapshot', $view);

        foreach (
            [
                'Финансы периода',
                'Риски',
                'Врачи',
                'Кабинеты',
                'Зарплата',
                'grossAfterKnownCosts',
                'openInvoiceBalance',
                'payrollAccrued',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $view);
        }

        $this->assertStringContainsString('approved: \'утверждено\'', $ui);
    }

    public function testManagerDashboardStartsWithManagementSnapshot(): void
    {
        $workspaceSeeder = $this->readFile(self::MODULE_ROOT . '/Tools/Installer/WorkspaceSeeder.php');
        $managerLayout = $this->methodCode($workspaceSeeder, 'managerDashboardLayout');
        $managerOptions = $this->methodCode($workspaceSeeder, 'managerDashletsOptions');

        $this->assertStringContainsString('ManagementSnapshot', $managerLayout);
        $this->assertStringContainsString('ed-manager-management-snapshot', $managerLayout);
        $this->assertStringContainsString('Управленческий срез', $managerOptions);
        $this->assertStringContainsString(
            "'ed-manager-management-snapshot', 'ManagementSnapshot', 0, 4, 4, 4",
            $managerLayout
        );
    }

    public function testLocalesAndDocsTrackManagementSnapshot(): void
    {
        foreach (['en_US', 'ru_RU', 'es_ES'] as $locale) {
            $global = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Global.json");

            $this->assertArrayHasKey('ManagementSnapshot', $global['dashlets']);
        }

        foreach (
            [
                self::ROOT . '/docs/current-state.md',
                self::ROOT . '/docs/release-notes.md',
                self::ROOT . '/docs/simple-stom-reports-payroll-integrations.md',
                self::ROOT . '/docs/espo-dental-product-development-plan.md',
            ] as $path
        ) {
            $this->assertStringContainsString('managementSnapshot', $this->readFile($path));
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
