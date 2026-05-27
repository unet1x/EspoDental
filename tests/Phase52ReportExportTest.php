<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase52ReportExportTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental/src';

    public function testReportExportEndpointCoversSeededReportSources(): void
    {
        $service = $this->readFile(self::MODULE_ROOT . '/Services/ReportService.php');
        $controller = $this->readFile(self::MODULE_ROOT . '/Controllers/Report.php');
        $routes = $this->readJson(self::MODULE_ROOT . '/Resources/routes.json');
        $paths = array_column($routes, 'route');

        $this->assertContains('/EspoDental/Report/export', $paths);
        $this->assertStringContainsString('getActionExport', $controller);
        $this->assertStringContainsString('exportReport', $controller);
        $this->assertStringContainsString('public function exportReport', $service);

        foreach (
            [
                'payments',
                'finance',
                'service_profitability',
                'material_finance',
                'doctor_utilization',
                'cabinet_utilization',
                'patient_funnel',
                'appointments',
                'inventory',
                'payroll',
            ] as $source
        ) {
            $this->assertStringContainsString("'{$source}'", $service);
        }

        foreach (
            [
                'buildPaymentsExport',
                'buildFinanceExport',
                'buildServiceProfitabilityExport',
                'buildMaterialFinanceExport',
                'buildPatientFunnelExport',
                'buildPayrollExport',
                'renderExportCsv',
                'renderExportJson',
                'Unsupported report export source',
                'Unsupported report export format',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $service);
        }
    }

    public function testManagementSnapshotDashletCanDownloadCsvExport(): void
    {
        $view = $this->readFile(self::CLIENT_ROOT . '/views/dashlets/management-snapshot.js');

        foreach (
            [
                'exportManagementSnapshot',
                'EspoDental/Report/export',
                "source: 'finance'",
                "format: 'csv'",
                'downloadExport',
                'new Blob',
                'createObjectURL',
                'Экспорт CSV',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $view);
        }
    }

    public function testDocsTrackReportExportContract(): void
    {
        foreach (
            [
                self::ROOT . '/docs/current-state.md',
                self::ROOT . '/docs/release-notes.md',
                self::ROOT . '/docs/simple-stom-reports-payroll-integrations.md',
                self::ROOT . '/docs/espo-dental-product-development-plan.md',
                self::ROOT . '/docs/simple-stom-migration-plan.md',
            ] as $path
        ) {
            $doc = $this->readFile($path);

            $this->assertStringContainsString('Report/export', $doc);
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
