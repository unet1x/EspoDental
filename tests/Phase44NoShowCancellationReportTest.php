<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase44NoShowCancellationReportTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental/src';

    public function testNoShowCancellationReportEndpointIsRegistered(): void
    {
        $service = (string) file_get_contents(self::MODULE_ROOT . '/Services/ReportService.php');
        $controller = (string) file_get_contents(self::MODULE_ROOT . '/Controllers/Report.php');
        $routes = json_decode((string) file_get_contents(self::MODULE_ROOT . '/Resources/routes.json'), true);
        $paths = array_column($routes, 'route');

        $this->assertStringContainsString('getNoShowCancellations', $service);
        $this->assertStringContainsString('Appointment::STATUS_NO_SHOW', $service);
        $this->assertStringContainsString('Appointment::STATUS_CANCELLED', $service);
        $this->assertStringContainsString('noShowRate', $service);
        $this->assertStringContainsString('cancellationRate', $service);
        $this->assertStringContainsString('issueRate', $service);
        $this->assertStringContainsString('getActionNoShowCancellations', $controller);
        $this->assertContains('/EspoDental/Report/noShowCancellations', $paths);
    }

    public function testNoShowCancellationDashletExistsAndUsesReportEndpoint(): void
    {
        $dashlet = json_decode(
            (string) file_get_contents(self::MODULE_ROOT . '/Resources/metadata/dashlets/NoShowCancellations.json'),
            true
        );
        $view = (string) file_get_contents(self::CLIENT_ROOT . '/views/dashlets/no-show-cancellations.js');

        $this->assertSame('espo-dental:views/dashlets/no-show-cancellations', $dashlet['view']);
        $this->assertSame('Appointment', $dashlet['aclScope']);
        $this->assertStringContainsString("name: 'NoShowCancellations'", $view);
        $this->assertStringContainsString('EspoDental/Report/noShowCancellations', $view);
        $this->assertStringContainsString('noShowCount', $view);
        $this->assertStringContainsString('cancellationCount', $view);
        $this->assertStringContainsString('issueRate', $view);
    }

    public function testManagerWorkspaceIncludesNoShowCancellations(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Tools/Installer/WorkspaceSeeder.php');
        $managerLayout = $this->methodCode($code, 'managerDashboardLayout');
        $managerOptions = $this->methodCode($code, 'managerDashletsOptions');

        $this->assertStringContainsString('NoShowCancellations', $managerLayout);
        $this->assertStringContainsString('ed-manager-no-show-cancellations', $managerLayout);
        $this->assertStringContainsString('Неявки и отмены', $managerOptions);
    }

    public function testNoShowCancellationLabelsAndDocsArePresent(): void
    {
        foreach (['en_US', 'ru_RU', 'es_ES'] as $locale) {
            $global = json_decode(
                (string) file_get_contents(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Global.json"),
                true
            );

            $this->assertArrayHasKey('NoShowCancellations', $global['dashlets']);
        }

        $currentState = (string) file_get_contents(self::ROOT . '/docs/current-state.md');
        $releaseNotes = (string) file_get_contents(self::ROOT . '/docs/release-notes.md');

        $this->assertStringContainsString('no-show and cancellation report', $currentState);
        $this->assertStringContainsString('No-show and cancellation report', $releaseNotes);
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
