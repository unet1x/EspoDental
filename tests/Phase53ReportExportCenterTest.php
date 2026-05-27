<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase53ReportExportCenterTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental/src';

    public function testReportExportCenterDashletUsesExportEndpoint(): void
    {
        $dashlet = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/dashlets/ReportExportCenter.json');
        $view = $this->readFile(self::CLIENT_ROOT . '/views/dashlets/report-export-center.js');

        $this->assertSame('espo-dental:views/dashlets/report-export-center', $dashlet['view']);
        $this->assertSame('ReportDefinition', $dashlet['aclScope']);
        $this->assertSame(50, $dashlet['options']['fields']['displayRecords']['default']);
        $this->assertStringContainsString("name: 'ReportExportCenter'", $view);
        $this->assertStringContainsString('EspoDental/Report/export', $view);

        foreach (
            [
                'reportExportPreview',
                'reportExportCsv',
                'reportExportJson',
                'downloadExport',
                'new Blob',
                'finance',
                'service_profitability',
                'patient_funnel',
                'payroll',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $view);
        }
    }

    public function testManagerDashboardPlacesExportCenterBelowManagementSnapshot(): void
    {
        $workspaceSeeder = $this->readFile(self::MODULE_ROOT . '/Tools/Installer/WorkspaceSeeder.php');
        $managerLayout = $this->methodCode($workspaceSeeder, 'managerDashboardLayout');
        $managerOptions = $this->methodCode($workspaceSeeder, 'managerDashletsOptions');

        $this->assertStringContainsString('ReportExportCenter', $managerLayout);
        $this->assertStringContainsString('ed-manager-report-export', $managerLayout);
        $this->assertStringContainsString('Экспорт отчетов', $managerOptions);
        $this->assertStringContainsString(
            "'ed-manager-management-snapshot', 'ManagementSnapshot', 0, 4, 4, 4",
            $managerLayout
        );
        $this->assertStringContainsString(
            "'ed-manager-report-export', 'ReportExportCenter', 0, 8, 4, 4",
            $managerLayout
        );
    }

    public function testLocalesAndDocsTrackReportExportCenter(): void
    {
        foreach (['en_US', 'ru_RU', 'es_ES'] as $locale) {
            $global = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Global.json");

            $this->assertArrayHasKey('ReportExportCenter', $global['dashlets']);
        }

        foreach (
            [
                self::ROOT . '/docs/current-state.md',
                self::ROOT . '/docs/release-notes.md',
                self::ROOT . '/docs/simple-stom-reports-payroll-integrations.md',
                self::ROOT . '/docs/espo-dental-product-development-plan.md',
                self::ROOT . '/docs/simple-stom-migration-plan.md',
            ] as $path
        ) {
            $this->assertStringContainsString('ReportExportCenter', $this->readFile($path));
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
