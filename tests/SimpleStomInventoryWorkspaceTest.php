<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class SimpleStomInventoryWorkspaceTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental/src';

    public function testInventoryWorkspaceEndpointAndServiceContractExist(): void
    {
        $routes = $this->readJson(self::MODULE_ROOT . '/Resources/routes.json');
        $paths = array_column($routes, 'route');
        $controller = $this->readFile(self::MODULE_ROOT . '/Controllers/Inventory.php');
        $service = $this->readFile(self::MODULE_ROOT . '/Services/InventoryService.php');

        $this->assertContains('/EspoDental/Inventory/workspace', $paths);
        $this->assertStringContainsString('getActionWorkspace', $controller);
        $this->assertStringContainsString('getWorkspace', $service);

        foreach (
            [
                'getWarehouseRows',
                'getStockLotRows',
                'getLowStockRows',
                'getExpiringLotRows',
                'getFutureOrderCandidates',
                'getCabinetIssueRows',
                'getRecentMovements',
                'InventoryWarehouse::ENTITY_TYPE',
                'InventoryStockLot::ENTITY_TYPE',
                'StockMovement::ENTITY_TYPE',
                'LowStockAlert::ENTITY_TYPE',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $service);
        }
    }

    public function testInventoryWorkspaceDashletRendersOperationalSections(): void
    {
        $dashlet = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/dashlets/InventoryWorkspace.json');
        $view = $this->readFile(self::CLIENT_ROOT . '/views/dashlets/inventory-workspace.js');

        $this->assertSame('espo-dental:views/dashlets/inventory-workspace', $dashlet['view']);
        $this->assertSame('InventoryWarehouse', $dashlet['aclScope']);
        $this->assertStringContainsString("name: 'InventoryWorkspace'", $view);
        $this->assertStringContainsString('EspoDental/Inventory/workspace', $view);

        foreach (
            [
                'data-name="warehouseId"',
                'Склады',
                'Партии выбранного склада',
                'Сроки годности',
                'Низкий остаток',
                'Кандидаты к заказу',
                'Выдача в кабинеты',
                'Последние движения',
                '#InventoryWarehouse/view/',
                '#InventoryStockLot/view/',
                '#StockMovement/view/',
                '#Material/view/',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $view);
        }
    }

    public function testStockDashboardUsesInventoryWorkspaceAsPrimarySurface(): void
    {
        $workspaceSeeder = $this->readFile(self::MODULE_ROOT . '/Tools/Installer/WorkspaceSeeder.php');
        $stockLayout = $this->methodCode($workspaceSeeder, 'stockDashboardLayout');
        $stockOptions = $this->methodCode($workspaceSeeder, 'stockDashletsOptions');

        $this->assertStringContainsString('InventoryWorkspace', $stockLayout);
        $this->assertStringContainsString('ed-stock-inventory-workspace', $stockLayout);
        $this->assertStringContainsString('InventoryStatus', $stockLayout);
        $this->assertStringContainsString('Рабочее место склада', $stockOptions);
    }

    public function testLocalesAndDocsTrackInventoryWorkspacePass(): void
    {
        foreach (['en_US', 'ru_RU', 'es_ES'] as $locale) {
            $global = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Global.json");

            $this->assertArrayHasKey('InventoryWorkspace', $global['dashlets']);
        }

        $doc = $this->readFile(self::ROOT . '/docs/simple-stom-services-inventory.md');
        $plan = $this->readFile(self::ROOT . '/docs/simple-stom-migration-plan.md');

        $this->assertStringContainsString('inventory workspace', $doc);
        $this->assertStringContainsString('Pass 4 - Inventory Workspace', $plan);
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
