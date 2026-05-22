<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase27PatientFinancialsPanelTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental';

    public function testPatientFinancialsEndpointSummarizesInvoicesAndPayments(): void
    {
        $controller = (string) file_get_contents(self::MODULE_ROOT . '/Controllers/Patient.php');
        $servicePath = self::MODULE_ROOT . '/Services/PatientFinancialService.php';
        $service = (string) file_get_contents($servicePath);

        $this->assertFileExists($servicePath);
        $this->assertStringContainsString('getActionFinancials', $controller);
        $this->assertStringContainsString('PatientFinancialService', $controller);
        $this->assertStringContainsString("checkScope('Invoice', 'read')", $controller);
        $this->assertStringContainsString("checkScope('Payment', 'read')", $controller);

        $this->assertStringContainsString('getPatientFinancials', $service);
        $this->assertStringContainsString("'openInvoiceBalance'", $service);
        $this->assertStringContainsString("'unallocatedCredit'", $service);
        $this->assertStringContainsString("'openInvoices'", $service);
        $this->assertStringContainsString("'recentPayments'", $service);
        $this->assertStringContainsString('Invoice::STATUS_ISSUED', $service);
        $this->assertStringContainsString('Invoice::STATUS_PARTIAL_PAID', $service);
        $this->assertStringContainsString("'balance>' => 0", $service);
        $this->assertStringContainsString('Payment::STATUS_COMPLETED', $service);
        $this->assertStringContainsString('Payment::STATUS_REFUNDED', $service);
        $this->assertStringContainsString('sumUnallocatedCredit', $service);
        $this->assertStringContainsString('localIssuedAt', $service);
        $this->assertStringContainsString('localPaidAt', $service);
    }

    public function testPatientDetailRendersFinancialsPanelBetweenHistoryAndFiles(): void
    {
        $viewPath = self::CLIENT_ROOT . '/src/views/patient/record/detail.js';
        $view = (string) file_get_contents($viewPath);

        $this->assertStringContainsString('Patient/action/financials', $view);
        $this->assertStringContainsString('patient-financials-panel', $view);
        $this->assertStringContainsString('patient-financials-body', $view);
        $this->assertStringContainsString('renderFinancialSummary', $view);
        $this->assertStringContainsString('renderOpenInvoices', $view);
        $this->assertStringContainsString('renderRecentPayments', $view);
        $this->assertStringContainsString('#Invoice/view/', $view);
        $this->assertStringContainsString('#Payment/view/', $view);
        $this->assertStringContainsString('Open Invoice Balance', $view);
        $this->assertStringContainsString('Unallocated Credit', $view);
        $this->assertStringContainsString('[data-name="patient-history-panel"]', $view);
        $this->assertStringContainsString('[data-name="patient-financials-panel"]', $view);
    }

    public function testPatientFinancialLabelsAreLocalized(): void
    {
        foreach (['en_US', 'ru_RU', 'es_ES'] as $locale) {
            $labels = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Patient.json")['labels'];

            $expectedLabels = [
                'Financials',
                'Current Balance',
                'Open Invoice Balance',
                'Unallocated Credit',
                'Open Invoices',
                'Recent Payments',
            ];

            foreach ($expectedLabels as $label) {
                $this->assertArrayHasKey($label, $labels);
            }
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
