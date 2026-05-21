<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase24StockMovementIntegrityTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';

    public function testMaterialStockFieldsAreDerivedFromMovements(): void
    {
        $material = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Material.json');
        $calculator = (string) file_get_contents(self::MODULE_ROOT . '/Tools/StockCalculator.php');
        $guard = (string) file_get_contents(self::MODULE_ROOT . '/Hooks/Material/GuardDerivedStock.php');
        $controller = (string) file_get_contents(self::MODULE_ROOT . '/Controllers/Material.php');

        $this->assertTrue($material['fields']['currentStock']['readOnly']);
        $this->assertTrue($material['fields']['stockLevel']['readOnly']);
        $this->assertStringContainsString('StockMovement::ENTITY_TYPE', $calculator);
        $this->assertStringContainsString('$movement->getSignedQuantity()', $calculator);
        $this->assertStringContainsString("set('currentStock', \$total)", $calculator);
        $this->assertStringContainsString("set('stockLevel', \$material->computeLevel())", $calculator);

        $this->assertStringContainsString('Material stock is derived from StockMovement records', $guard);
        $this->assertStringContainsString("fieldChanged(\$entity, 'currentStock')", $guard);
        $this->assertStringContainsString("fieldChanged(\$entity, 'stockLevel')", $guard);
        $this->assertStringContainsString('Material::LEVEL_OUT', $guard);

        $this->assertStringContainsString('assertDerivedStockIsNotChanged', $controller);
        $this->assertStringContainsString('patchActionUpdate', $controller);
        $this->assertStringContainsString('putActionUpdate', $controller);
        $this->assertStringContainsString('Material stock is derived from StockMovement records', $controller);
    }

    public function testPostedStockMovementsAreImmutableCorrectionRecords(): void
    {
        $clientDefs = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/clientDefs/StockMovement.json');
        $movement = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/StockMovement.json');
        $guard = (string) file_get_contents(self::MODULE_ROOT . '/Hooks/StockMovement/PreventPostedMutation.php');
        $normalize = (string) file_get_contents(self::MODULE_ROOT . '/Hooks/StockMovement/Normalize.php');

        $this->assertTrue($clientDefs['removeDisabled']);
        $this->assertContains('adjustment', $movement['fields']['type']['options']);
        $this->assertContains('writeoff', $movement['fields']['type']['options']);
        $this->assertContains('return', $movement['fields']['type']['options']);

        $this->assertStringContainsString('beforeSave', $guard);
        $this->assertStringContainsString('beforeRemove', $guard);
        $this->assertStringContainsString('$entity->isNew()', $guard);
        $this->assertStringContainsString('espodentalAllowStockMovementMutation', $guard);
        $this->assertStringContainsString('create a correction movement', $guard);

        $this->assertStringContainsString('afterSave', $normalize);
        $this->assertStringContainsString('afterRemove', $normalize);
        $this->assertStringContainsString('$this->calculator->recalculate($material)', $normalize);
    }

    public function testRolesPatchStockMovementToCreateOnly(): void
    {
        $roleSeeder = (string) file_get_contents(self::MODULE_ROOT . '/Tools/Installer/RoleSeeder.php');

        $this->assertStringContainsString('FORCE_PATCH_SCOPES', $roleSeeder);
        $this->assertStringContainsString("'StockMovement',", $roleSeeder);
        $this->assertStringContainsString(
            "\$manager['StockMovement']        = \$row('yes', 'all', 'no', 'no', 'no');",
            $roleSeeder
        );
        $this->assertStringContainsString(
            "'StockMovement'        => \$row('yes', 'all', 'no', 'no', 'no'),",
            $roleSeeder
        );
        $this->assertStringContainsString('(array) $data[$scope] === $row', $roleSeeder);
    }

    public function testOpeningAndVisitConsumptionCreateMovements(): void
    {
        $workspaceSeeder = (string) file_get_contents(
            self::MODULE_ROOT . '/Tools/Installer/WorkspaceSeeder.php'
        );
        $stockService = (string) file_get_contents(self::MODULE_ROOT . '/Services/StockService.php');

        $this->assertStringContainsString('ensureOpeningStock', $workspaceSeeder);
        $this->assertStringContainsString("getRDBRepository('StockMovement')->getNew()", $workspaceSeeder);
        $this->assertStringContainsString("'type' => 'receipt'", $workspaceSeeder);

        $this->assertStringContainsString('consumePreparedMaterialLines', $stockService);
        $this->assertStringContainsString('StockMovement::TYPE_CONSUMPTION', $stockService);
        $this->assertStringContainsString('sourceVisitMaterialLineId', $stockService);
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
