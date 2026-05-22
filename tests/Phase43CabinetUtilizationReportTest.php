<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase43CabinetUtilizationReportTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental/src';

    public function testCabinetUtilizationReportEndpointIsRegistered(): void
    {
        $service = (string) file_get_contents(self::MODULE_ROOT . '/Services/ReportService.php');
        $controller = (string) file_get_contents(self::MODULE_ROOT . '/Controllers/Report.php');
        $routes = json_decode((string) file_get_contents(self::MODULE_ROOT . '/Resources/routes.json'), true);
        $paths = array_column($routes, 'route');

        $this->assertStringContainsString('getCabinetUtilization', $service);
        $this->assertStringContainsString('Cabinet::ENTITY_TYPE', $service);
        $this->assertStringContainsString('Appointment::BLOCKING_STATUSES', $service);
        $this->assertStringContainsString('occupiedMinutes', $service);
        $this->assertStringContainsString('availableMinutes', $service);
        $this->assertStringContainsString('utilizationPercent', $service);
        $this->assertStringContainsString('getActionCabinetUtilization', $controller);
        $this->assertContains('/EspoDental/Report/cabinetUtilization', $paths);
    }

    public function testCabinetUtilizationDashletExistsAndUsesReportEndpoint(): void
    {
        $dashlet = json_decode(
            (string) file_get_contents(self::MODULE_ROOT . '/Resources/metadata/dashlets/CabinetUtilization.json'),
            true
        );
        $view = (string) file_get_contents(self::CLIENT_ROOT . '/views/dashlets/cabinet-utilization.js');

        $this->assertSame('espo-dental:views/dashlets/cabinet-utilization', $dashlet['view']);
        $this->assertSame('Appointment', $dashlet['aclScope']);
        $this->assertSame(8, $dashlet['options']['fields']['workStartHour']['default']);
        $this->assertSame(21, $dashlet['options']['fields']['workEndHour']['default']);
        $this->assertStringContainsString("name: 'CabinetUtilization'", $view);
        $this->assertStringContainsString('EspoDental/Report/cabinetUtilization', $view);
        $this->assertStringContainsString('occupiedMinutes', $view);
        $this->assertStringContainsString('utilizationPercent', $view);
    }

    public function testManagerWorkspaceIncludesCabinetUtilization(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Tools/Installer/WorkspaceSeeder.php');
        $managerLayout = $this->methodCode($code, 'managerDashboardLayout');
        $managerOptions = $this->methodCode($code, 'managerDashletsOptions');

        $this->assertStringContainsString('CabinetUtilization', $managerLayout);
        $this->assertStringContainsString('ed-manager-cabinet-utilization', $managerLayout);
        $this->assertStringContainsString('Загрузка кабинетов', $managerOptions);
        $this->assertStringContainsString('workStartHour', $managerOptions);
        $this->assertStringContainsString('workEndHour', $managerOptions);
    }

    public function testCabinetUtilizationLabelsAndDocsArePresent(): void
    {
        foreach (['en_US', 'ru_RU', 'es_ES'] as $locale) {
            $global = json_decode(
                (string) file_get_contents(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Global.json"),
                true
            );

            $this->assertArrayHasKey('CabinetUtilization', $global['dashlets']);
        }

        $currentState = (string) file_get_contents(self::ROOT . '/docs/current-state.md');
        $releaseNotes = (string) file_get_contents(self::ROOT . '/docs/release-notes.md');

        $this->assertStringContainsString('cabinet utilization report', $currentState);
        $this->assertStringContainsString('Cabinet utilization report', $releaseNotes);
    }

    private function methodCode(string $code, string $method): string
    {
        $start = strpos($code, 'private function ' . $method);
        $this->assertIsInt($start);

        $next = strpos($code, 'private function ', $start + 1);
        $this->assertIsInt($next);

        return substr($code, $start, $next - $start);
    }
}
