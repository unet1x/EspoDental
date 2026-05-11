<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase7MetadataTest extends TestCase
{
    private const MODULE_ROOT = __DIR__ . '/../src/files/custom/Espo/Modules/EspoDental';
    private const ENTITIES = [
        'MaterialCategory', 'Material', 'StockMovement',
        'ServiceMaterial', 'LowStockAlert',
    ];
    private const LOCALES = ['ru_RU', 'en_US', 'es_ES'];

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

    public function testScopesExist(): void
    {
        foreach (self::ENTITIES as $e) {
            $scope = $this->readJson(self::MODULE_ROOT . "/Resources/metadata/scopes/{$e}.json");
            $this->assertTrue($scope['entity']);
        }
    }

    public function testMaterialUnitOptions(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Material.json');
        foreach (['pcs', 'ml', 'g', 'kg', 'l', 'box', 'pack', 'syringe', 'tube', 'cartridge'] as $u) {
            $this->assertContains($u, $def['fields']['unit']['options']);
        }
    }

    public function testMaterialStockLevelOptions(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Material.json');
        $this->assertSame(['', 'ok', 'low', 'critical', 'out'], $def['fields']['stockLevel']['options']);
    }

    public function testStockMovementTypes(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/StockMovement.json');
        foreach ([
            'receipt', 'consumption', 'writeoff',
            'transfer_out', 'transfer_in', 'adjustment', 'return',
        ] as $t) {
            $this->assertContains($t, $def['fields']['type']['options']);
        }
    }

    public function testServiceLinksServiceMaterials(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Service.json');
        $this->assertArrayHasKey('serviceMaterials', $def['links']);
        $this->assertSame('hasMany', $def['links']['serviceMaterials']['type']);
    }

    public function testMaterialHasRelations(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Material.json');
        foreach (['category', 'movements', 'serviceMaterials', 'alerts'] as $l) {
            $this->assertArrayHasKey($l, $def['links'], "Missing Material link {$l}");
        }
    }

    public function testServiceMaterialUniqueIndex(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/ServiceMaterial.json');
        $this->assertTrue($def['indexes']['service_material']['unique']);
    }

    public function testStockMovementInboundConstantsInPhp(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Entities/StockMovement.php');
        $this->assertStringContainsString('INBOUND_TYPES', $code);
        $this->assertStringContainsString('TYPE_RECEIPT', $code);
        $this->assertStringContainsString('TYPE_CONSUMPTION', $code);
        $this->assertStringContainsString('deriveDirection', $code);
    }

    public function testMaterialComputeLevelLogic(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Entities/Material.php');
        $this->assertStringContainsString('computeLevel', $code);
        $this->assertStringContainsString('LEVEL_OUT', $code);
        $this->assertStringContainsString('LEVEL_CRITICAL', $code);
    }

    public function testHooksAndToolsExist(): void
    {
        $this->assertFileExists(self::MODULE_ROOT . '/Hooks/StockMovement/Normalize.php');
        $this->assertFileExists(self::MODULE_ROOT . '/Tools/StockCalculator.php');
    }

    public function testStockServiceExists(): void
    {
        $this->assertFileExists(self::MODULE_ROOT . '/Services/StockService.php');
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Services/StockService.php');
        $this->assertStringContainsString('consumeForVisit', $code);
    }

    public function testThresholdJobRegistered(): void
    {
        $jobs = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/app/scheduledJobs.json');
        $this->assertArrayHasKey('EspoDentalCheckStockThresholds', $jobs);
        $this->assertFileExists(self::MODULE_ROOT . '/Jobs/CheckStockThresholds.php');
    }

    public function testVisitServiceCallsStock(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Services/VisitService.php');
        $this->assertStringContainsString('StockService', $code);
        $this->assertStringContainsString('consumeForVisit', $code);
    }

    public function testLocalesExist(): void
    {
        foreach (self::LOCALES as $locale) {
            foreach (self::ENTITIES as $entity) {
                $path = self::MODULE_ROOT . "/Resources/i18n/{$locale}/{$entity}.json";
                $this->assertFileExists($path);
                $loc = $this->readJson($path);
                $this->assertArrayHasKey('fields', $loc);
            }
        }
    }

    public function testGlobalScopeNamesExtended(): void
    {
        foreach (self::LOCALES as $locale) {
            $global = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Global.json");
            foreach (self::ENTITIES as $entity) {
                $this->assertArrayHasKey($entity, $global['scopeNames']);
                $this->assertArrayHasKey($entity, $global['scopeNamesPlural']);
            }
        }
    }

    public function testAfterInstallScopesUpdated(): void
    {
        $code = (string) file_get_contents(
            __DIR__ . '/../src/files/custom/Espo/Modules/EspoDental/Tools/Installer/RoleSeeder.php'
        );
        foreach (self::ENTITIES as $entity) {
            $this->assertStringContainsString("'{$entity}'", $code);
        }
    }
}
