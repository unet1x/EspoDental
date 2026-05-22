<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase45InventoryReportTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental/src';

    public function testInventoryStatusReportEndpointIsRegistered(): void
    {
        $service = (string) file_get_contents(self::MODULE_ROOT . '/Services/ReportService.php');
        $controller = (string) file_get_contents(self::MODULE_ROOT . '/Controllers/Report.php');
        $routes = json_decode((string) file_get_contents(self::MODULE_ROOT . '/Resources/routes.json'), true);
        $paths = array_column($routes, 'route');

        $this->assertStringContainsString('getInventoryStatus', $service);
        $this->assertStringContainsString('Material::ENTITY_TYPE', $service);
        $this->assertStringContainsString('StockMovement::ENTITY_TYPE', $service);
        $this->assertStringContainsString('inventoryValue', $service);
        $this->assertStringContainsString('inboundQuantity', $service);
        $this->assertStringContainsString('outboundQuantity', $service);
        $this->assertStringContainsString('netQuantity', $service);
        $this->assertStringContainsString('getActionInventoryStatus', $controller);
        $this->assertContains('/EspoDental/Report/inventoryStatus', $paths);
    }

    public function testInventoryStatusDashletExistsAndUsesReportEndpoint(): void
    {
        $dashlet = json_decode(
            (string) file_get_contents(self::MODULE_ROOT . '/Resources/metadata/dashlets/InventoryStatus.json'),
            true
        );
        $view = (string) file_get_contents(self::CLIENT_ROOT . '/views/dashlets/inventory-status.js');

        $this->assertSame('espo-dental:views/dashlets/inventory-status', $dashlet['view']);
        $this->assertSame('Material', $dashlet['aclScope']);
        $this->assertStringContainsString("name: 'InventoryStatus'", $view);
        $this->assertStringContainsString('EspoDental/Report/inventoryStatus', $view);
        $this->assertStringContainsString('currentStock', $view);
        $this->assertStringContainsString('outboundQuantity', $view);
        $this->assertStringContainsString('inventoryValue', $view);
    }

    public function testManagerAndStockWorkspacesIncludeInventoryStatus(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Tools/Installer/WorkspaceSeeder.php');
        $managerLayout = $this->methodCode($code, 'managerDashboardLayout');
        $managerOptions = $this->methodCode($code, 'managerDashletsOptions');
        $stockLayout = $this->methodCode($code, 'stockDashboardLayout');
        $stockOptions = $this->methodCode($code, 'stockDashletsOptions');

        $this->assertStringContainsString('InventoryStatus', $managerLayout);
        $this->assertStringContainsString('ed-manager-inventory-status', $managerLayout);
        $this->assertStringContainsString('Состояние склада', $managerOptions);
        $this->assertStringContainsString('InventoryStatus', $stockLayout);
        $this->assertStringContainsString('ed-stock-inventory-status', $stockLayout);
        $this->assertStringContainsString('Состояние склада', $stockOptions);
    }

    public function testInventoryStatusLabelsAndDocsArePresent(): void
    {
        foreach (['en_US', 'ru_RU', 'es_ES'] as $locale) {
            $global = json_decode(
                (string) file_get_contents(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Global.json"),
                true
            );

            $this->assertArrayHasKey('InventoryStatus', $global['dashlets']);
        }

        $currentState = (string) file_get_contents(self::ROOT . '/docs/current-state.md');
        $releaseNotes = (string) file_get_contents(self::ROOT . '/docs/release-notes.md');

        $this->assertStringContainsString('inventory status report', $currentState);
        $this->assertStringContainsString('Inventory status report', $releaseNotes);
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
