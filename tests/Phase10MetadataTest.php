<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase10MetadataTest extends TestCase
{
    private const MODULE_ROOT = __DIR__ . '/../src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = __DIR__ . '/../src/files/client/custom/modules/espo-dental/src';
    private const ENTITIES = ['SalaryProfile', 'SalaryEntry', 'SalaryBonus'];
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

    public function testScopesAndDefsExist(): void
    {
        foreach (self::ENTITIES as $e) {
            $scope = $this->readJson(self::MODULE_ROOT . "/Resources/metadata/scopes/{$e}.json");
            $this->assertTrue($scope['entity']);
            $def = $this->readJson(self::MODULE_ROOT . "/Resources/metadata/entityDefs/{$e}.json");
            $this->assertArrayHasKey('name', $def['fields']);
            $this->assertArrayHasKey('user', $def['fields']);
        }
    }

    public function testSalaryProfileEnums(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/SalaryProfile.json');
        foreach (['doctor', 'assistant', 'administrator', 'stock_manager', 'manager', 'other'] as $r) {
            $this->assertContains($r, $def['fields']['roleType']['options']);
        }
        foreach (['fixed_monthly', 'hourly', 'per_visit', 'none'] as $rt) {
            $this->assertContains($rt, $def['fields']['rateType']['options']);
        }
    }

    public function testSalaryEntryStatusOptions(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/SalaryEntry.json');
        foreach (['draft', 'approved', 'paid', 'cancelled'] as $s) {
            $this->assertContains($s, $def['fields']['status']['options']);
        }
        foreach (['baseAmount', 'revenueAmount', 'assistantAmount', 'bonusAmount', 'deductionAmount', 'totalAmount'] as $f) {
            $this->assertArrayHasKey($f, $def['fields']);
        }
        $this->assertTrue($def['fields']['totalAmount']['readOnly']);
        $this->assertArrayHasKey('bonuses', $def['links']);
        $this->assertArrayHasKey('paidPayment', $def['links']);
    }

    public function testSalaryBonusKinds(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/SalaryBonus.json');
        foreach (['bonus', 'allowance', 'penalty'] as $k) {
            $this->assertContains($k, $def['fields']['kind']['options']);
        }
        foreach (['pending', 'included', 'cancelled'] as $s) {
            $this->assertContains($s, $def['fields']['status']['options']);
        }
    }

    public function testEntityPhpFilesAndConstants(): void
    {
        foreach (self::ENTITIES as $e) {
            $path = self::MODULE_ROOT . "/Entities/{$e}.php";
            $this->assertFileExists($path);
            $code = (string) file_get_contents($path);
            $this->assertStringContainsString("const ENTITY_TYPE = '{$e}'", $code);
        }
        $entry = (string) file_get_contents(self::MODULE_ROOT . '/Entities/SalaryEntry.php');
        foreach (['STATUS_DRAFT', 'STATUS_APPROVED', 'STATUS_PAID', 'STATUS_CANCELLED'] as $c) {
            $this->assertStringContainsString($c, $entry);
        }
    }

    public function testRecalculateTotalsHookExists(): void
    {
        $hook = self::MODULE_ROOT . '/Hooks/SalaryEntry/RecalculateTotals.php';
        $this->assertFileExists($hook);
        $code = (string) file_get_contents($hook);
        $this->assertStringContainsString('beforeSave', $code);
        $this->assertStringContainsString('totalAmount', $code);
        $this->assertStringContainsString('static int $order', $code);
    }

    public function testToolsAndServiceExist(): void
    {
        $this->assertFileExists(self::MODULE_ROOT . '/Tools/SalaryCalculator.php');
        $this->assertFileExists(self::MODULE_ROOT . '/Services/SalaryService.php');
        $svc = (string) file_get_contents(self::MODULE_ROOT . '/Services/SalaryService.php');
        foreach (['buildEntry', 'approveEntry', 'payEntry', 'cancelEntry'] as $m) {
            $this->assertStringContainsString($m, $svc);
        }
        $calc = (string) file_get_contents(self::MODULE_ROOT . '/Tools/SalaryCalculator.php');
        foreach (['calculateDoctorRevenue', 'calculateAssistantRevenue', 'aggregateBonuses', 'calculateBase'] as $m) {
            $this->assertStringContainsString($m, $calc);
        }
    }

    public function testControllerAndRoutes(): void
    {
        $ctrl = self::MODULE_ROOT . '/Controllers/SalaryEntry.php';
        $this->assertFileExists($ctrl);
        $code = (string) file_get_contents($ctrl);
        foreach (['postActionBuild', 'postActionApprove', 'postActionPay', 'postActionCancel'] as $m) {
            $this->assertStringContainsString($m, $code);
        }
        $routes = json_decode((string) file_get_contents(self::MODULE_ROOT . '/Resources/routes.json'), true);
        $paths = array_column($routes, 'route');
        foreach ([
            '/EspoDental/SalaryEntry/build',
            '/EspoDental/SalaryEntry/approve',
            '/EspoDental/SalaryEntry/pay',
            '/EspoDental/SalaryEntry/cancel',
        ] as $p) {
            $this->assertContains($p, $paths);
        }
    }

    public function testClientHandlersExist(): void
    {
        foreach (['build', 'approve', 'pay', 'cancel'] as $h) {
            $this->assertFileExists(self::CLIENT_ROOT . "/handlers/salary-entry/{$h}.js");
        }
    }

    public function testBoolFiltersAndSelectDefs(): void
    {
        $defs = [
            'SalaryProfile' => ['onlyActive'],
            'SalaryEntry' => ['thisMonth', 'draftOnly', 'paidOnly'],
            'SalaryBonus' => ['pendingOnly'],
        ];
        foreach ($defs as $entity => $filters) {
            $sel = $this->readJson(self::MODULE_ROOT . "/Resources/metadata/selectDefs/{$entity}.json");
            foreach ($filters as $f) {
                $this->assertArrayHasKey($f, $sel['boolFilterClassNameMap']);
                $cls = ucfirst($f);
                $path = self::MODULE_ROOT . "/Classes/Select/{$entity}/BoolFilters/{$cls}.php";
                $this->assertFileExists($path);
            }
        }
    }

    public function testPayrollDashlet(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/dashlets/PayrollThisMonth.json');
        $this->assertSame('espo-dental:views/dashlets/payroll-this-month', $def['view']);
        $this->assertFileExists(self::CLIENT_ROOT . '/views/dashlets/payroll-this-month.js');
    }

    public function testGlobalScopeNamesExtended(): void
    {
        foreach (self::LOCALES as $locale) {
            $g = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Global.json");
            foreach (self::ENTITIES as $e) {
                $this->assertArrayHasKey($e, $g['scopeNames']);
                $this->assertArrayHasKey($e, $g['scopeNamesPlural']);
            }
            $this->assertArrayHasKey('PayrollThisMonth', $g['dashlets']);
        }
    }

    public function testPerEntityLocalesExist(): void
    {
        foreach (self::LOCALES as $locale) {
            foreach (self::ENTITIES as $e) {
                $loc = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/{$e}.json");
                $this->assertArrayHasKey('fields', $loc);
            }
        }
    }

    public function testAfterInstallContainsSalaryScopes(): void
    {
        $code = (string) file_get_contents(__DIR__ . '/../src/scripts/AfterInstall.php');
        foreach (self::ENTITIES as $e) {
            $this->assertStringContainsString("'{$e}'", $code);
        }
    }
}
