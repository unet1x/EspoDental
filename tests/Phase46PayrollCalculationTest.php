<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase46PayrollCalculationTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental/src';

    public function testSalaryCalculatorUsesVisitServiceLineAmountForRevenueBasis(): void
    {
        $calculator = (string) file_get_contents(self::MODULE_ROOT . '/Tools/SalaryCalculator.php');
        $serviceLineDef = json_decode(
            (string) file_get_contents(self::MODULE_ROOT . '/Resources/metadata/entityDefs/VisitServiceLine.json'),
            true
        );

        $this->assertArrayHasKey('amount', $serviceLineDef['fields']);
        $this->assertArrayNotHasKey('amountTotal', $serviceLineDef['fields']);
        $this->assertStringContainsString('getRDBRepository(VisitServiceLine::ENTITY_TYPE)', $calculator);
        $this->assertStringContainsString('$line->getAmount()', $calculator);
        $this->assertStringNotContainsString('amountTotal', $calculator);
        $this->assertStringNotContainsString('COUNT:DISTINCT', $calculator);
    }

    public function testBuildEntryAcceptsHoursWorkedBeforeBaseCalculation(): void
    {
        $service = (string) file_get_contents(self::MODULE_ROOT . '/Services/SalaryService.php');
        $controller = (string) file_get_contents(self::MODULE_ROOT . '/Controllers/SalaryEntry.php');
        $handler = (string) file_get_contents(self::CLIENT_ROOT . '/handlers/salary-entry/build.js');

        $this->assertStringContainsString('float $hoursWorked = 0.0', $service);
        $this->assertStringContainsString("set('hoursWorked', max(0.0, \$hoursWorked))", $service);
        $this->assertLessThan(
            strpos($service, 'calculateBase('),
            strpos($service, "set('hoursWorked', max(0.0, \$hoursWorked))")
        );
        $this->assertStringContainsString('$data->hoursWorked ?? 0', $controller);
        $this->assertStringContainsString('$profileId, $hoursWorked', $controller);
        $this->assertStringContainsString("hoursWorked: model.get('hoursWorked') || 0", $handler);
    }

    public function testSupportedPayrollRateTypesAreDocumentedAndCalculated(): void
    {
        $profile = json_decode(
            (string) file_get_contents(self::MODULE_ROOT . '/Resources/metadata/entityDefs/SalaryProfile.json'),
            true
        );
        $calculator = (string) file_get_contents(self::MODULE_ROOT . '/Tools/SalaryCalculator.php');
        $currentState = (string) file_get_contents(self::ROOT . '/docs/current-state.md');
        $releaseNotes = (string) file_get_contents(self::ROOT . '/docs/release-notes.md');

        $this->assertContains('fixed_monthly', $profile['fields']['rateType']['options']);
        $this->assertContains('hourly', $profile['fields']['rateType']['options']);
        $this->assertContains('per_visit', $profile['fields']['rateType']['options']);
        $this->assertStringContainsString('RATE_FIXED_MONTHLY => $profile->getBaseRate()', $calculator);
        $this->assertStringContainsString('RATE_HOURLY => $profile->getBaseRate() * $hoursWorked', $calculator);
        $this->assertStringContainsString('RATE_PER_VISIT => $profile->getBaseRate() * $visitsCount', $calculator);
        $this->assertStringContainsString('payroll calculation hardening', $currentState);
        $this->assertStringContainsString('Payroll calculation hardening', $releaseNotes);
    }
}
