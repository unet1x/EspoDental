<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase42DoctorProductivityReportTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental/src';

    public function testDoctorProductivityReportEndpointIsRegistered(): void
    {
        $service = (string) file_get_contents(self::MODULE_ROOT . '/Services/ReportService.php');
        $controller = (string) file_get_contents(self::MODULE_ROOT . '/Controllers/Report.php');
        $routes = json_decode((string) file_get_contents(self::MODULE_ROOT . '/Resources/routes.json'), true);
        $paths = array_column($routes, 'route');

        $this->assertStringContainsString('getDoctorProductivity', $service);
        $this->assertStringContainsString('Visit::STATUS_FINISHED', $service);
        $this->assertStringContainsString('VisitServiceLine::ENTITY_TYPE', $service);
        $this->assertStringContainsString('averageVisitAmount', $service);
        $this->assertStringContainsString('getActionDoctorProductivity', $controller);
        $this->assertContains('/EspoDental/Report/doctorProductivity', $paths);
    }

    public function testDoctorProductivityDashletExistsAndUsesReportEndpoint(): void
    {
        $dashlet = json_decode(
            (string) file_get_contents(self::MODULE_ROOT . '/Resources/metadata/dashlets/DoctorProductivity.json'),
            true
        );
        $view = (string) file_get_contents(self::CLIENT_ROOT . '/views/dashlets/doctor-productivity.js');

        $this->assertSame('espo-dental:views/dashlets/doctor-productivity', $dashlet['view']);
        $this->assertSame('Visit', $dashlet['aclScope']);
        $this->assertStringContainsString("name: 'DoctorProductivity'", $view);
        $this->assertStringContainsString('EspoDental/Report/doctorProductivity', $view);
        $this->assertStringContainsString('serviceLineCount', $view);
        $this->assertStringContainsString('averageVisitAmount', $view);
    }

    public function testManagerWorkspaceIncludesDoctorProductivity(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Tools/Installer/WorkspaceSeeder.php');
        $command = (string) file_get_contents(self::MODULE_ROOT . '/Tools/Console/SeedRolesCommand.php');
        $managerLayout = $this->methodCode($code, 'managerDashboardLayout');
        $managerOptions = $this->methodCode($code, 'managerDashletsOptions');

        $this->assertStringContainsString('DoctorProductivity', $managerLayout);
        $this->assertStringContainsString('ed-manager-doctor-productivity', $managerLayout);
        $this->assertStringContainsString('Продуктивность врачей', $managerOptions);
        $this->assertStringContainsString('json_encode($existing->get(\'layout\'))', $code);
        $this->assertStringContainsString('prepared %d dashboard template(s)', $command);
    }

    public function testDoctorProductivityLabelsAndDocsArePresent(): void
    {
        foreach (['en_US', 'ru_RU', 'es_ES'] as $locale) {
            $global = json_decode(
                (string) file_get_contents(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Global.json"),
                true
            );

            $this->assertArrayHasKey('DoctorProductivity', $global['dashlets']);
        }

        $currentState = (string) file_get_contents(self::ROOT . '/docs/current-state.md');
        $releaseNotes = (string) file_get_contents(self::ROOT . '/docs/release-notes.md');

        $this->assertStringContainsString('doctor productivity report', $currentState);
        $this->assertStringContainsString('Doctor productivity report', $releaseNotes);
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
