<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class SimpleStomCashDeskTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental/src';

    public function testCashDeskWorkspaceStartsFromInvoicesAndShiftClosing(): void
    {
        $routes = $this->readFile(self::MODULE_ROOT . '/Resources/routes.json');
        $service = $this->readFile(self::MODULE_ROOT . '/Services/CashDeskService.php');
        $controller = $this->readFile(self::MODULE_ROOT . '/Controllers/CashDesk.php');
        $dashlet = $this->readFile(self::CLIENT_ROOT . '/views/dashlets/cash-desk-workspace.js');

        $this->assertStringContainsString('/EspoDental/CashDesk/workspace', $routes);
        $this->assertStringContainsString('/EspoDental/CashDesk/closeShift', $routes);
        $this->assertStringContainsString('getWorkspace', $service);
        $this->assertStringContainsString('getInvoiceRows', $service);
        $this->assertStringContainsString('getClosingPreview', $service);
        $this->assertStringContainsString('closeShift', $service);
        $this->assertStringContainsString('CashShift::ENTITY_TYPE', $service);
        $this->assertStringContainsString('getActionWorkspace', $controller);
        $this->assertStringContainsString('postActionCloseShift', $controller);
        $this->assertStringContainsString('CashDeskWorkspace', $dashlet);
        $this->assertStringContainsString('Только неоплаченные', $dashlet);
        $this->assertStringContainsString('Закрыть смену', $dashlet);
        $this->assertStringNotContainsString('Новая оплата', $dashlet);
        $this->assertStringContainsString('data-invoice-id', $dashlet);
    }

    public function testCashShiftAdjustmentAndSequenceEntitiesExist(): void
    {
        foreach (['CashShift', 'FinancialAdjustment', 'FinancialDocumentSequence'] as $entity) {
            $this->readJson(self::MODULE_ROOT . "/Resources/metadata/scopes/{$entity}.json");
            $this->readJson(self::MODULE_ROOT . "/Resources/metadata/clientDefs/{$entity}.json");
            $this->readJson(self::MODULE_ROOT . "/Resources/metadata/entityDefs/{$entity}.json");
            $this->readJson(self::MODULE_ROOT . "/Resources/layouts/{$entity}/detail.json");
            $this->readJson(self::MODULE_ROOT . "/Resources/layouts/{$entity}/list.json");
            $this->assertFileExists(self::MODULE_ROOT . "/Entities/{$entity}.php");
            $this->assertFileExists(self::MODULE_ROOT . "/Controllers/{$entity}.php");
        }

        $shift = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/CashShift.json');
        $adjustment = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/FinancialAdjustment.json');
        $sequence = $this->readJson(
            self::MODULE_ROOT . '/Resources/metadata/entityDefs/FinancialDocumentSequence.json'
        );

        $this->assertSame(['open', 'closed'], $shift['fields']['status']['options']);
        $this->assertSame(['write_off', 'complaint', 'manual_charge'], $adjustment['fields']['type']['options']);
        $this->assertTrue($adjustment['fields']['reason']['required']);
        $this->assertSame(['invoice', 'act', 'receipt'], $sequence['fields']['documentType']['options']);
        $this->assertSame(6, $sequence['fields']['digits']['default']);
    }

    public function testPaymentSupportsAdvanceCryptoAndCorrectionLedger(): void
    {
        $payment = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Payment.json');
        $entity = $this->readFile(self::MODULE_ROOT . '/Entities/Payment.php');
        $service = $this->readFile(self::MODULE_ROOT . '/Services/PaymentService.php');
        $controller = $this->readFile(self::MODULE_ROOT . '/Controllers/Payment.php');

        foreach (
            [
                'isReversed',
                'reversedAt',
                'reversedBy',
                'reverseReason',
                'externalReference',
                'cryptoAsset',
                'cryptoAmount',
                'cashShift',
            ] as $field
        ) {
            $this->assertArrayHasKey($field, $payment['fields']);
        }

        $this->assertStringContainsString('METHOD_ADVANCE', $entity);
        $this->assertStringContainsString('applyAdvance', $service);
        $this->assertStringContainsString('getAvailableAdvance', $service);
        $this->assertStringContainsString('Advance balance is insufficient', $service);
        $this->assertStringContainsString('advanceDebitPaymentId', $service);
        $this->assertStringContainsString('postActionApplyAdvance', $controller);
        $this->assertStringContainsString('cryptoAsset', $controller);
        $this->assertStringContainsString('externalReference', $controller);
    }

    public function testFinancialAdjustmentRequiresReasonAndSignedAmount(): void
    {
        $hook = $this->readFile(self::MODULE_ROOT . '/Hooks/FinancialAdjustment/Normalize.php');

        $this->assertStringContainsString('Financial adjustment reason is required', $hook);
        $this->assertStringContainsString('Financial adjustment amount must be positive', $hook);
        $this->assertStringContainsString('signedAmount', $hook);
        $this->assertStringContainsString('TYPE_MANUAL_CHARGE', $hook);
    }

    public function testRolesDashboardsLocalesAndDocsTrackCashDeskStage(): void
    {
        $roleSeeder = $this->readFile(self::MODULE_ROOT . '/Tools/Installer/RoleSeeder.php');
        $workspaceSeeder = $this->readFile(self::MODULE_ROOT . '/Tools/Installer/WorkspaceSeeder.php');

        foreach (['CashShift', 'FinancialAdjustment', 'FinancialDocumentSequence'] as $entity) {
            $this->assertStringContainsString($entity, $roleSeeder);
            $this->assertStringContainsString($entity, $workspaceSeeder);
        }
        $this->assertStringContainsString('CashDeskWorkspace', $workspaceSeeder);

        foreach (['en_US', 'ru_RU', 'es_ES'] as $locale) {
            $global = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Global.json");
            foreach (['CashShift', 'FinancialAdjustment', 'FinancialDocumentSequence'] as $entity) {
                $this->assertArrayHasKey($entity, $global['scopeNames']);
                $this->assertArrayHasKey($entity, $global['scopeNamesPlural']);
                $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/{$entity}.json");
            }
        }

        $readme = $this->readFile(self::ROOT . '/README.md');
        $plan = $this->readFile(self::ROOT . '/docs/simple-stom-migration-plan.md');
        $doc = $this->readFile(self::ROOT . '/docs/simple-stom-cash-desk.md');

        $this->assertStringContainsString('docs/simple-stom-cash-desk.md', $readme);
        $this->assertStringContainsString('| 11. Cash desk and shift closing | Completed |', $plan);
        $this->assertStringContainsString('CashShift', $doc);
        $this->assertStringContainsString('FinancialAdjustment', $doc);
        $this->assertStringContainsString('FinancialDocumentSequence', $doc);
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
