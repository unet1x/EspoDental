<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class SimpleStomReportsPayrollIntegrationsTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';

    public function testReportDefinitionEntityCoversSimpleStomSources(): void
    {
        foreach (['ReportDefinition'] as $entity) {
            $this->readJson(self::MODULE_ROOT . "/Resources/metadata/scopes/{$entity}.json");
            $this->readJson(self::MODULE_ROOT . "/Resources/metadata/clientDefs/{$entity}.json");
            $this->readJson(self::MODULE_ROOT . "/Resources/metadata/entityDefs/{$entity}.json");
            $this->readJson(self::MODULE_ROOT . "/Resources/layouts/{$entity}/detail.json");
            $this->readJson(self::MODULE_ROOT . "/Resources/layouts/{$entity}/list.json");
            $this->assertFileExists(self::MODULE_ROOT . "/Entities/{$entity}.php");
            $this->assertFileExists(self::MODULE_ROOT . "/Controllers/{$entity}.php");
        }

        $definition = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/ReportDefinition.json');
        $expectedSources = [
            'payments',
            'finance',
            'service_profitability',
            'material_finance',
            'doctor_utilization',
            'cabinet_utilization',
            'patient_funnel',
            'appointments',
            'inventory',
            'payroll',
        ];

        $this->assertSame($expectedSources, $definition['fields']['source']['options']);
        foreach (['filters', 'columns', 'groupings', 'metrics', 'sort'] as $field) {
            $this->assertSame('jsonObject', $definition['fields'][$field]['type']);
        }

        $workspaceSeeder = $this->readFile(self::MODULE_ROOT . '/Tools/Installer/WorkspaceSeeder.php');
        $this->assertStringContainsString('ensureReportDefinitions', $workspaceSeeder);
        $this->assertStringContainsString('SimpleStom: выручка и платежи', $workspaceSeeder);
        $this->assertStringContainsString('SimpleStom: зарплата', $workspaceSeeder);
        $this->assertStringContainsString("'source' => 'payroll'", $workspaceSeeder);
    }

    public function testPayrollEntriesExposeTransparentSourceBreakdown(): void
    {
        $entry = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/SalaryEntry.json');
        $layout = $this->readFile(self::MODULE_ROOT . '/Resources/layouts/SalaryEntry/detail.json');
        $service = $this->readFile(self::MODULE_ROOT . '/Services/SalaryService.php');

        $this->assertArrayHasKey('sourceBreakdown', $entry['fields']);
        $this->assertSame('jsonObject', $entry['fields']['sourceBreakdown']['type']);
        $this->assertTrue($entry['fields']['sourceBreakdown']['readOnly']);
        $this->assertStringContainsString('sourceBreakdown', $layout);

        foreach (
            [
                "'doctor'",
                "'assistant'",
                "'manualAdjustments'",
                "'rule'",
                "'sourceType' => 'reception'",
                "'sourceType' => 'manual_adjustment'",
                "'profileId'",
                "'rateType'",
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $service);
        }
    }

    public function testIntegrationSettingsAndSecretsAreRegisteredWithoutAiScope(): void
    {
        foreach (['IntegrationSettings', 'IntegrationSecret'] as $entity) {
            $this->readJson(self::MODULE_ROOT . "/Resources/metadata/scopes/{$entity}.json");
            $this->readJson(self::MODULE_ROOT . "/Resources/metadata/clientDefs/{$entity}.json");
            $this->readJson(self::MODULE_ROOT . "/Resources/metadata/entityDefs/{$entity}.json");
            $this->readJson(self::MODULE_ROOT . "/Resources/layouts/{$entity}/detail.json");
            $this->readJson(self::MODULE_ROOT . "/Resources/layouts/{$entity}/list.json");
            $this->assertFileExists(self::MODULE_ROOT . "/Entities/{$entity}.php");
            $this->assertFileExists(self::MODULE_ROOT . "/Controllers/{$entity}.php");
        }

        $settings = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/IntegrationSettings.json');
        $secret = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/IntegrationSecret.json');
        $service = $this->readFile(self::MODULE_ROOT . '/Services/IntegrationSettingsService.php');
        $settingsHook = $this->readFile(self::MODULE_ROOT . '/Hooks/IntegrationSettings/Normalize.php');
        $secretHook = $this->readFile(self::MODULE_ROOT . '/Hooks/IntegrationSecret/Normalize.php');

        $this->assertSame(['smtp', 'whatsapp', 'telegram'], $settings['fields']['integrationType']['options']);
        $this->assertNotContains('ai', $settings['fields']['integrationType']['options']);
        $this->assertSame(['provider_token', 'smtp_password', 'api_key'], $secret['fields']['secretKind']['options']);
        $this->assertSame('password', $secret['fields']['secretValue']['type']);
        $this->assertSame('bool', $secret['fields']['valuePresent']['type']);

        $this->assertStringContainsString('sanitizeSecret', $service);
        $this->assertStringContainsString("'valuePresent'", $service);
        $this->assertStringNotContainsString("'secretValue'", $service);
        $this->assertStringContainsString("set('name'", $settingsHook);
        $this->assertStringContainsString("set('valuePresent'", $secretHook);
    }

    public function testSettingsUiExposesSmtpAndExistingMessagingProviders(): void
    {
        $settings = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Settings.json');
        $layout = $this->readFile(self::MODULE_ROOT . '/Resources/layouts/Settings/espoDentalSettings.json');

        foreach (
            [
                'espoDentalSmtpEnabled',
                'espoDentalSmtpHost',
                'espoDentalSmtpPort',
                'espoDentalSmtpUsername',
                'espoDentalSmtpPassword',
                'espoDentalSmtpEncryption',
                'espoDentalSmtpFromAddress',
                'espoDentalTelegramEnabled',
                'espoDentalWhatsAppEnabled',
            ] as $field
        ) {
            $this->assertArrayHasKey($field, $settings['fields']);
            $this->assertStringContainsString($field, $layout);
        }

        $this->assertSame(['none', 'tls', 'ssl'], $settings['fields']['espoDentalSmtpEncryption']['options']);
        $this->assertSame('password', $settings['fields']['espoDentalSmtpPassword']['type']);
    }

    public function testRolesLocalesAndDocsTrackStage(): void
    {
        $roleSeeder = $this->readFile(self::MODULE_ROOT . '/Tools/Installer/RoleSeeder.php');
        $workspaceSeeder = $this->readFile(self::MODULE_ROOT . '/Tools/Installer/WorkspaceSeeder.php');

        foreach (['ReportDefinition', 'IntegrationSettings', 'IntegrationSecret'] as $entity) {
            $this->assertStringContainsString($entity, $roleSeeder);
            $this->assertStringContainsString($entity, $workspaceSeeder);
        }

        foreach (['en_US', 'ru_RU', 'es_ES'] as $locale) {
            $global = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Global.json");
            foreach (['ReportDefinition', 'IntegrationSettings', 'IntegrationSecret'] as $entity) {
                $this->assertArrayHasKey($entity, $global['scopeNames']);
                $this->assertArrayHasKey($entity, $global['scopeNamesPlural']);
                $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/{$entity}.json");
            }
            $salary = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/SalaryEntry.json");
            $settings = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Settings.json");
            $this->assertArrayHasKey('sourceBreakdown', $salary['fields']);
            $this->assertArrayHasKey('espoDentalSmtpEnabled', $settings['fields']);
        }

        $readme = $this->readFile(self::ROOT . '/README.md');
        $plan = $this->readFile(self::ROOT . '/docs/simple-stom-migration-plan.md');
        $doc = $this->readFile(self::ROOT . '/docs/simple-stom-reports-payroll-integrations.md');

        $this->assertStringContainsString('docs/simple-stom-reports-payroll-integrations.md', $readme);
        $this->assertStringContainsString('| 12. Reports, payroll and integrations | Completed |', $plan);
        $this->assertStringContainsString('ReportDefinition', $doc);
        $this->assertStringContainsString('sourceBreakdown', $doc);
        $this->assertStringContainsString('IntegrationSettings', $doc);
        $this->assertStringContainsString('MCP and AI integration behavior remains explicitly out of scope', $doc);
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
