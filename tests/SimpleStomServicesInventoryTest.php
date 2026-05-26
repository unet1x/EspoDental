<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class SimpleStomServicesInventoryTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';

    public function testServiceCatalogCarriesSimpleStomNormsAndCabinetRequirements(): void
    {
        $service = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Service.json');
        $serviceMaterial = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/ServiceMaterial.json');

        $this->assertSame('jsonObject', $service['fields']['cabinetRequirements']['type']);
        $this->assertArrayHasKey('price', $service['fields']);
        $this->assertArrayHasKey('duration', $service['fields']);
        $this->assertArrayHasKey('color', $service['fields']);
        $this->assertArrayHasKey('serviceMaterials', $service['links']);

        $this->assertArrayHasKey('unit', $serviceMaterial['fields']);
        $this->assertArrayHasKey('isRequired', $serviceMaterial['fields']);
        $this->assertTrue($serviceMaterial['fields']['isRequired']['default']);

        $matcher = $this->readFile(self::MODULE_ROOT . '/Tools/CabinetRequirementMatcher.php');
        $seeder = $this->readFile(self::MODULE_ROOT . '/Tools/Installer/WorkspaceSeeder.php');

        $this->assertStringContainsString('class CabinetRequirementMatcher', $matcher);
        $this->assertStringContainsString('equipmentAny', $matcher);
        $this->assertStringContainsString('cabinetRequirements', $seeder);
        $this->assertStringContainsString("'хирургия'", $seeder);
        $this->assertStringContainsString("'ортодонтия'", $seeder);
    }

    public function testMaterialExposesPurchasingAndExpirationSemantics(): void
    {
        $material = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Material.json');
        $entity = $this->readFile(self::MODULE_ROOT . '/Entities/Material.php');

        foreach (
            [
                'consumptionUnit',
                'purchasingUnit',
                'conversionFactor',
                'trackExpiration',
                'reorderUrl',
            ] as $field
        ) {
            $this->assertArrayHasKey($field, $material['fields']);
        }

        $this->assertArrayHasKey('stockLots', $material['links']);
        $this->assertStringContainsString('getConsumptionUnit', $entity);
        $this->assertStringContainsString('getPurchasingUnit', $entity);
        $this->assertStringContainsString('getConversionFactor', $entity);
        $this->assertStringContainsString('tracksExpiration', $entity);
    }

    public function testWarehouseAndStockLotEntitiesAreRegistered(): void
    {
        foreach (['InventoryWarehouse', 'InventoryStockLot'] as $entity) {
            $this->readJson(self::MODULE_ROOT . "/Resources/metadata/scopes/{$entity}.json");
            $this->readJson(self::MODULE_ROOT . "/Resources/metadata/clientDefs/{$entity}.json");
            $this->readJson(self::MODULE_ROOT . "/Resources/metadata/entityDefs/{$entity}.json");
            $this->readJson(self::MODULE_ROOT . "/Resources/layouts/{$entity}/detail.json");
            $this->readJson(self::MODULE_ROOT . "/Resources/layouts/{$entity}/list.json");
            $this->assertFileExists(self::MODULE_ROOT . "/Entities/{$entity}.php");
            $this->assertFileExists(self::MODULE_ROOT . "/Controllers/{$entity}.php");
        }

        $warehouse = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/InventoryWarehouse.json');
        $lot = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/InventoryStockLot.json');

        $this->assertSame(['main', 'satellite'], $warehouse['fields']['warehouseType']['options']);
        $this->assertArrayHasKey('quantityInPurchasingUnits', $lot['fields']);
        $this->assertArrayHasKey('expiresAt', $lot['fields']);
        $this->assertArrayHasKey('sourceTransaction', $lot['fields']);
    }

    public function testStockMovementLinksWarehousesLotsAndManualCorrections(): void
    {
        $movement = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/StockMovement.json');
        $entity = $this->readFile(self::MODULE_ROOT . '/Entities/StockMovement.php');
        $normalize = $this->readFile(self::MODULE_ROOT . '/Hooks/StockMovement/Normalize.php');

        foreach (
            [
                'manual_increase',
                'manual_decrease',
                'manual_set',
                'inventory_count',
                'reception_usage',
            ] as $type
        ) {
            $this->assertContains($type, $movement['fields']['type']['options']);
        }

        foreach (['sourceWarehouse', 'targetWarehouse', 'stockLot'] as $link) {
            $this->assertArrayHasKey($link, $movement['fields']);
            $this->assertArrayHasKey($link, $movement['links']);
        }

        $this->assertStringContainsString('MANUAL_CORRECTION_TYPES', $entity);
        $this->assertStringContainsString('Manual stock corrections require a reason', $normalize);
        $this->assertStringContainsString('Expiration date is required for this material', $normalize);
    }

    public function testInventoryServicePlansFefoConsumption(): void
    {
        $service = $this->readFile(self::MODULE_ROOT . '/Services/InventoryService.php');
        $lotHook = $this->readFile(self::MODULE_ROOT . '/Hooks/InventoryStockLot/Normalize.php');

        $this->assertStringContainsString('planFefoConsumption', $service);
        $this->assertStringContainsString('getFefoLots', $service);
        $this->assertStringContainsString("'9999-12-31'", $service);
        $this->assertStringContainsString('expiresAt', $service);
        $this->assertStringContainsString('receivedAt', $service);
        $this->assertStringContainsString('InventoryStockLot::ENTITY_TYPE', $service);
        $this->assertStringContainsString('Stock lot', $lotHook);
    }

    public function testSeederAndRolesExposeInventoryLayer(): void
    {
        $roleSeeder = $this->readFile(self::MODULE_ROOT . '/Tools/Installer/RoleSeeder.php');
        $workspaceSeeder = $this->readFile(self::MODULE_ROOT . '/Tools/Installer/WorkspaceSeeder.php');

        foreach (['InventoryWarehouse', 'InventoryStockLot'] as $entity) {
            $this->assertStringContainsString($entity, $roleSeeder);
            $this->assertStringContainsString($entity, $workspaceSeeder);
        }

        $this->assertStringContainsString('ensureInventoryWarehouses', $workspaceSeeder);
        $this->assertStringContainsString('ensureOpeningStockLot', $workspaceSeeder);
        $this->assertStringContainsString("'warehouseType' => 'main'", $workspaceSeeder);
        $this->assertStringContainsString("'warehouseType' => 'satellite'", $workspaceSeeder);
        $this->assertStringContainsString("'lotNumber' => 'OPENING'", $workspaceSeeder);
    }

    public function testLocalesAndDocsTrackServicesInventoryStage(): void
    {
        foreach (['en_US', 'ru_RU', 'es_ES'] as $locale) {
            $global = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Global.json");

            foreach (['InventoryWarehouse', 'InventoryStockLot'] as $entity) {
                $this->assertArrayHasKey($entity, $global['scopeNames']);
                $this->assertArrayHasKey($entity, $global['scopeNamesPlural']);
                $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/{$entity}.json");
            }
        }

        $readme = $this->readFile(self::ROOT . '/README.md');
        $plan = $this->readFile(self::ROOT . '/docs/simple-stom-migration-plan.md');
        $doc = $this->readFile(self::ROOT . '/docs/simple-stom-services-inventory.md');

        $this->assertStringContainsString('docs/simple-stom-services-inventory.md', $readme);
        $this->assertStringContainsString('| 10. Services and inventory | Completed |', $plan);
        $this->assertStringContainsString('InventoryWarehouse', $doc);
        $this->assertStringContainsString('InventoryStockLot', $doc);
        $this->assertStringContainsString('planFefoConsumption', $doc);
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
