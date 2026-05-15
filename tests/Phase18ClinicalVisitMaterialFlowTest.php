<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase18ClinicalVisitMaterialFlowTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';

    public function testVisitMaterialLineEntityIsWired(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/VisitMaterialLine.json');
        $scope = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/scopes/VisitMaterialLine.json');

        $this->assertTrue($scope['entity']);
        $this->assertSame('Visit', $def['links']['visit']['entity']);
        $this->assertSame('VisitServiceLine', $def['links']['visitServiceLine']['entity']);
        $this->assertSame('Material', $def['links']['material']['entity']);
        $this->assertTrue($def['fields']['quantity']['required']);
        $this->assertTrue($def['fields']['plannedQuantity']['readOnly']);
        $this->assertSame(
            ['visitServiceLineId', 'materialId'],
            $def['indexes']['visit_service_line_material']['columns']
        );

        $this->assertFileExists(self::MODULE_ROOT . '/Entities/VisitMaterialLine.php');
        $this->assertFileExists(self::MODULE_ROOT . '/Controllers/VisitMaterialLine.php');
        $this->assertFileExists(self::MODULE_ROOT . '/Resources/layouts/VisitMaterialLine/detail.json');
        $this->assertFileExists(self::MODULE_ROOT . '/Resources/layouts/VisitMaterialLine/list.json');
    }

    public function testVisitAndServiceLineExposeMaterialLines(): void
    {
        $visit = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Visit.json');
        $serviceLine = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/VisitServiceLine.json');
        $clientDefs = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/clientDefs/Visit.json');
        $visitDetail = $this->readJson(self::MODULE_ROOT . '/Resources/layouts/Visit/detail.json');
        $visitRelationships = $this->readJson(self::MODULE_ROOT . '/Resources/layouts/Visit/relationships.json');
        $serviceLineRelationships = $this->readJson(
            self::MODULE_ROOT . '/Resources/layouts/VisitServiceLine/relationships.json'
        );
        $encodedDetail = json_encode($visitDetail, JSON_THROW_ON_ERROR);

        $this->assertSame('VisitMaterialLine', $visit['links']['materialLines']['entity']);
        $this->assertSame('VisitMaterialLine', $serviceLine['links']['materialLines']['entity']);
        $this->assertContains('materialLines', $visitRelationships);
        $this->assertContains('materialLines', $serviceLineRelationships);
        $this->assertStringNotContainsString('assignedUser', $encodedDetail);
        $this->assertStringNotContainsString('teams', $encodedDetail);
        $this->assertSame(['createdAt', 'modifiedAt'], $clientDefs['defaultSidePanelFieldLists']['detail']);
        $this->assertSame(
            'isFinishVisitAvailable',
            $clientDefs['menu']['detail']['buttons'][0]['checkVisibilityFunction']
        );
    }

    public function testServiceLineHookCopiesMaterialNorms(): void
    {
        $hook = self::MODULE_ROOT . '/Hooks/VisitServiceLine/SyncMaterialLines.php';

        $this->assertFileExists($hook);
        $code = (string) file_get_contents($hook);
        $this->assertStringContainsString('afterSave', $code);
        $this->assertStringContainsString('ServiceMaterial::ENTITY_TYPE', $code);
        $this->assertStringContainsString('VisitMaterialLine::ENTITY_TYPE', $code);
        $this->assertStringContainsString('plannedQuantity', $code);
        $this->assertStringContainsString('isAutoCreated', $code);
        $this->assertStringContainsString('removeStaleAutoLines', $code);
    }

    public function testServiceLinePriceAlwaysFollowsCatalog(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Hooks/VisitServiceLine/RecalculateAmount.php');

        $this->assertStringContainsString('$service->getPrice()', $code);
        $this->assertStringContainsString('$service->get(\'priceCurrency\')', $code);
        $this->assertStringContainsString('$service->getVatRate()', $code);
    }

    public function testPreparedMaterialLinesDriveStockConsumption(): void
    {
        $stockService = (string) file_get_contents(self::MODULE_ROOT . '/Services/StockService.php');
        $movement = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/StockMovement.json');

        $this->assertStringContainsString('consumePreparedMaterialLines', $stockService);
        $this->assertStringContainsString('VisitMaterialLine::ENTITY_TYPE', $stockService);
        $this->assertStringContainsString('sourceVisitMaterialLineId', $stockService);
        $this->assertSame('VisitMaterialLine', $movement['links']['sourceVisitMaterialLine']['entity']);
    }

    public function testInvoicePdfBuilderUsesInjectableLanguageClass(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Tools/InvoicePdfBuilder.php');

        $this->assertStringContainsString('use Espo\\Core\\Utils\\Language;', $code);
        $this->assertStringNotContainsString('use Espo\\Core\\Language;', $code);
    }

    public function testRolesAndLocalesIncludeVisitMaterialLine(): void
    {
        $roleSeeder = (string) file_get_contents(self::MODULE_ROOT . '/Tools/Installer/RoleSeeder.php');

        $this->assertStringContainsString("'VisitMaterialLine'", $roleSeeder);
        $this->assertStringContainsString('array_key_exists($scope, $data)', $roleSeeder);

        foreach (['ru_RU', 'en_US', 'es_ES'] as $locale) {
            $this->assertFileExists(self::MODULE_ROOT . "/Resources/i18n/{$locale}/VisitMaterialLine.json");
            $global = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Global.json");
            $this->assertArrayHasKey('VisitMaterialLine', $global['scopeNames']);
            $this->assertArrayHasKey('VisitMaterialLine', $global['scopeNamesPlural']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        $this->assertFileExists($path);
        $data = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($data, "Invalid JSON: {$path}");

        return $data;
    }
}
