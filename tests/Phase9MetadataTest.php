<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase9MetadataTest extends TestCase
{
    private const MODULE_ROOT = __DIR__ . '/../src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = __DIR__ . '/../src/files/client/custom/modules/espo-dental/src';
    private const DASHLETS = [
        'TodaysAppointments', 'OpenInvoices', 'LowStockMaterials',
        'RecentVisits', 'MonthlyRevenue',
    ];
    private const LOCALES = ['ru_RU', 'en_US', 'es_ES'];
    private const SELECT_DEFS = [
        'Appointment' => ['today', 'upcoming', 'thisWeek', 'myDoctor'],
        'Invoice' => ['onlyOpen', 'onlyOverdue'],
        'Payment' => ['today', 'thisMonth'],
        'Visit' => ['today', 'thisMonth'],
        'Material' => ['onlyActive', 'lowStock', 'criticalStock'],
        'LowStockAlert' => ['onlyOpen'],
        'NotificationLog' => ['onlyFailed', 'today'],
    ];

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        $this->assertFileExists($path);
        $data = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($data, "Invalid JSON: $path");
        return $data;
    }

    public function testSelectDefsExist(): void
    {
        foreach (self::SELECT_DEFS as $entity => $filters) {
            $def = $this->readJson(self::MODULE_ROOT . "/Resources/metadata/selectDefs/{$entity}.json");
            $this->assertArrayHasKey('boolFilterClassNameMap', $def);
            foreach ($filters as $filter) {
                $this->assertArrayHasKey(
                    $filter,
                    $def['boolFilterClassNameMap'],
                    "Missing bool filter $filter in $entity"
                );
            }
        }
    }

    public function testBoolFilterClassesExist(): void
    {
        foreach (self::SELECT_DEFS as $entity => $filters) {
            foreach ($filters as $filter) {
                $cls = ucfirst($filter);
                $path = self::MODULE_ROOT . "/Classes/Select/{$entity}/BoolFilters/{$cls}.php";
                $this->assertFileExists($path, "Missing filter class for $entity::$filter");
                $code = (string) file_get_contents($path);
                $this->assertMatchesRegularExpression(
                    '/extends (RawBoolFilter|UserAwareRawBoolFilter)/',
                    $code
                );
                $this->assertStringContainsString('protected function buildWhereItem', $code);
            }
        }
    }

    public function testDateRangeHelperExists(): void
    {
        $path = self::MODULE_ROOT . '/Classes/Select/Common/DateRangeHelper.php';
        $this->assertFileExists($path);
        $code = (string) file_get_contents($path);
        foreach (['today', 'thisWeek', 'thisMonth', 'between'] as $m) {
            $this->assertStringContainsString("public static function {$m}", $code);
        }
    }

    public function testRawBoolFilterBridgeExists(): void
    {
        $path = self::MODULE_ROOT . '/Classes/Select/Common/RawBoolFilter.php';
        $this->assertFileExists($path);
        $code = (string) file_get_contents($path);
        $this->assertStringContainsString('implements Filter', $code);
        $this->assertStringContainsString('OrGroupBuilder $orGroupBuilder', $code);
    }

    public function testDashletMetadataExists(): void
    {
        foreach (self::DASHLETS as $d) {
            $def = $this->readJson(self::MODULE_ROOT . "/Resources/metadata/dashlets/{$d}.json");
            $this->assertArrayHasKey('view', $def);
            $this->assertStringStartsWith('espo-dental:views/dashlets/', $def['view']);
            $this->assertArrayHasKey('aclScope', $def);
        }
    }

    public function testDashletViewsExist(): void
    {
        $map = [
            'TodaysAppointments' => 'todays-appointments',
            'OpenInvoices' => 'open-invoices',
            'LowStockMaterials' => 'low-stock-materials',
            'RecentVisits' => 'recent-visits',
            'MonthlyRevenue' => 'monthly-revenue',
        ];
        foreach ($map as $kebab) {
            $path = self::CLIENT_ROOT . "/views/dashlets/{$kebab}.js";
            $this->assertFileExists($path);
        }
    }

    public function testReportServiceAndController(): void
    {
        $svc = self::MODULE_ROOT . '/Services/ReportService.php';
        $ctrl = self::MODULE_ROOT . '/Controllers/Report.php';
        $this->assertFileExists($svc);
        $this->assertFileExists($ctrl);
        $code = (string) file_get_contents($svc);
        $this->assertStringContainsString('getMonthlyRevenue', $code);
        $this->assertStringContainsString('getInvoiceSummary', $code);
        $this->assertStringContainsString('getLowStockSummary', $code);
        $ctrlCode = (string) file_get_contents($ctrl);
        $this->assertStringContainsString('getActionMonthlyRevenue', $ctrlCode);
        $this->assertStringContainsString('getActionInvoiceSummary', $ctrlCode);
        $this->assertStringContainsString('getActionLowStockSummary', $ctrlCode);
    }

    public function testReportRoutesRegistered(): void
    {
        $routes = json_decode((string) file_get_contents(self::MODULE_ROOT . '/Resources/routes.json'), true);
        $paths = array_column($routes, 'route');
        $this->assertContains('/EspoDental/Report/monthlyRevenue', $paths);
        $this->assertContains('/EspoDental/Report/invoiceSummary', $paths);
        $this->assertContains('/EspoDental/Report/lowStockSummary', $paths);
    }

    public function testGlobalI18nHasDashletsAndFilters(): void
    {
        foreach (self::LOCALES as $locale) {
            $g = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Global.json");
            $this->assertArrayHasKey('dashlets', $g);
            $this->assertArrayHasKey('boolFilters', $g);
            foreach (self::DASHLETS as $d) {
                $this->assertArrayHasKey($d, $g['dashlets']);
            }
            foreach (['today', 'thisWeek', 'thisMonth', 'onlyOpen', 'lowStock'] as $f) {
                $this->assertArrayHasKey($f, $g['boolFilters']);
            }
        }
    }

    public function testMonthlyRevenueRendererUsesAjax(): void
    {
        $code = (string) file_get_contents(self::CLIENT_ROOT . '/views/dashlets/monthly-revenue.js');
        $this->assertStringContainsString('EspoDental/Report/monthlyRevenue', $code);
        $this->assertStringContainsString('renderChart', $code);
    }
}
