<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase6MetadataTest extends TestCase
{
    private const MODULE_ROOT = __DIR__ . '/../src/files/custom/Espo/Modules/EspoDental';
    private const ENTITIES = ['Invoice', 'InvoiceLine', 'Payment'];
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

    public function testEntityScopesExist(): void
    {
        foreach (self::ENTITIES as $entity) {
            $scope = $this->readJson(self::MODULE_ROOT . "/Resources/metadata/scopes/{$entity}.json");
            $this->assertSame('EspoDental', $scope['module']);
            $this->assertTrue($scope['entity']);
        }
    }

    public function testInvoiceStatusHasSixOptions(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Invoice.json');
        $this->assertSame(
            ['draft', 'issued', 'partial_paid', 'paid', 'storno', 'cancelled'],
            $def['fields']['status']['options']
        );
    }

    public function testInvoiceNumberIsUnique(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Invoice.json');
        $this->assertTrue($def['indexes']['number']['unique']);
    }

    public function testInvoiceLinksLines(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Invoice.json');
        $this->assertSame('hasMany', $def['links']['lines']['type']);
        $this->assertSame('InvoiceLine', $def['links']['lines']['entity']);
        $this->assertSame('hasMany', $def['links']['payments']['type']);
        $this->assertSame('Payment', $def['links']['payments']['entity']);
    }

    public function testPaymentEnums(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Payment.json');
        $this->assertSame(
            ['cash', 'card', 'bank_transfer', 'online', 'terminal', 'other'],
            $def['fields']['method']['options']
        );
        $this->assertSame(
            ['pending', 'completed', 'cancelled', 'refunded'],
            $def['fields']['status']['options']
        );
        $this->assertSame(['in', 'out'], $def['fields']['direction']['options']);
    }

    public function testPatientLinksInvoicesAndPayments(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Patient.json');
        $this->assertSame('hasMany', $def['links']['invoices']['type']);
        $this->assertSame('hasMany', $def['links']['payments']['type']);
    }

    public function testVisitLinksInvoices(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Visit.json');
        $this->assertArrayHasKey('invoices', $def['links']);
        $this->assertSame('hasMany', $def['links']['invoices']['type']);
    }

    public function testHooksExist(): void
    {
        $this->assertFileExists(self::MODULE_ROOT . '/Hooks/Invoice/AssignNumber.php');
        $this->assertFileExists(self::MODULE_ROOT . '/Hooks/Payment/AssignNumber.php');
        $this->assertFileExists(self::MODULE_ROOT . '/Hooks/Payment/UpdateInvoiceAndBalance.php');
        $this->assertFileExists(self::MODULE_ROOT . '/Hooks/InvoiceLine/RecalculateAmount.php');
    }

    public function testToolsExist(): void
    {
        $this->assertFileExists(self::MODULE_ROOT . '/Tools/InvoiceCalculator.php');
        $this->assertFileExists(self::MODULE_ROOT . '/Tools/InvoicePdfBuilder.php');
        $this->assertFileExists(self::MODULE_ROOT . '/Tools/PatientBalanceCalculator.php');
    }

    public function testServicesAndControllersExist(): void
    {
        $this->assertFileExists(self::MODULE_ROOT . '/Services/InvoiceService.php');
        $this->assertFileExists(self::MODULE_ROOT . '/Services/PaymentService.php');
        $this->assertFileExists(self::MODULE_ROOT . '/Controllers/Invoice.php');
        $this->assertFileExists(self::MODULE_ROOT . '/Controllers/Payment.php');

        $code = (string) file_get_contents(self::MODULE_ROOT . '/Controllers/Invoice.php');
        $this->assertStringContainsString('postActionIssue', $code);
        $this->assertStringContainsString('postActionStorno', $code);
        $this->assertStringContainsString('postActionBuildPdf', $code);

        $code = (string) file_get_contents(self::MODULE_ROOT . '/Controllers/Payment.php');
        $this->assertStringContainsString('postActionAccept', $code);
        $this->assertStringContainsString('postActionRefund', $code);
    }

    public function testClientHandlersExist(): void
    {
        $root = __DIR__ . '/../src/files/client/custom/modules/espo-dental/src/handlers';
        $this->assertFileExists($root . '/invoice/issue.js');
        $this->assertFileExists($root . '/invoice/storno.js');
        $this->assertFileExists($root . '/invoice/accept-payment.js');
        $this->assertFileExists($root . '/invoice/print-pdf.js');
        $this->assertFileExists($root . '/payment/refund.js');
    }

    public function testEntityPhpClassesExist(): void
    {
        foreach (self::ENTITIES as $entity) {
            $this->assertFileExists(self::MODULE_ROOT . "/Entities/{$entity}.php");
        }
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
        $code = (string) file_get_contents(__DIR__ . '/../src/scripts/AfterInstall.php');
        $this->assertStringContainsString("'Invoice'", $code);
        $this->assertStringContainsString("'InvoiceLine'", $code);
        $this->assertStringContainsString("'Payment'", $code);
    }

    public function testInvoiceEntityHasStatusConstants(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Entities/Invoice.php');
        foreach ([
            'STATUS_DRAFT', 'STATUS_ISSUED', 'STATUS_PARTIAL_PAID',
            'STATUS_PAID', 'STATUS_STORNO', 'STATUS_CANCELLED',
        ] as $const) {
            $this->assertStringContainsString($const, $code);
        }
    }

    public function testPaymentEntityHasMethodAndDirectionConstants(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Entities/Payment.php');
        $this->assertStringContainsString('DIRECTION_IN', $code);
        $this->assertStringContainsString('DIRECTION_OUT', $code);
        $this->assertStringContainsString('STATUS_COMPLETED', $code);
    }

    public function testVisitServiceDependsOnInvoiceService(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Services/VisitService.php');
        $this->assertStringContainsString('InvoiceService', $code);
        $this->assertStringContainsString('buildFromVisit', $code);
    }
}
