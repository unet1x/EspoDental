<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class SimpleStomDemoEnvironmentTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';

    public function testDemoSeedCommandIsRegisteredAndSeparateFromBootstrap(): void
    {
        $commands = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/app/consoleCommands.json');
        $command = $this->readFile(self::MODULE_ROOT . '/Tools/Console/DemoSeedCommand.php');
        $bootstrap = $this->readFile(self::MODULE_ROOT . '/Tools/Console/SeedRolesCommand.php');

        $this->assertArrayHasKey('espoDentalDemoSeed', $commands);
        $this->assertSame(
            'Espo\\Modules\\EspoDental\\Tools\\Console\\DemoSeedCommand',
            $commands['espoDentalDemoSeed']['className']
        );
        $this->assertStringContainsString('espo-dental-demo-seed', $command);
        $this->assertStringContainsString('WorkspaceSeeder', $command);
        $this->assertStringContainsString('DemoSeeder', $command);
        $this->assertStringContainsString('report definition', $bootstrap);
    }

    public function testDemoSeederCoversAcceptanceDataSlices(): void
    {
        $seeder = $this->readFile(self::MODULE_ROOT . '/Tools/Installer/DemoSeeder.php');

        foreach (
            [
                'demo.manager',
                'demo.admin',
                'demo.doctor',
                'demo.assistant',
                'demo.stock',
                'DoctorShift',
                'PreliminaryPatient',
                'AppointmentWaitlistEntry',
                'AssistantActionProposal',
                'HealthQuestionnaire',
                'QuestionnaireToken',
                'PatientPortalSession',
                'PatientPortalEvent',
                'AppointmentRescheduleRequest',
                'VisitServiceLine',
                'VisitMaterialLine',
                'ToothChartSnapshot',
                'InvoiceLine',
                'Payment',
                'CashShift',
                'InventoryStockLot',
                'StockMovement',
                'LowStockAlert',
                'SalaryProfile',
                'SalaryBonus',
                'SalaryEntry',
                'IntegrationSettings',
                'IntegrationSecret',
                'sourceBreakdown',
                'DEMO SimpleStom',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $seeder);
        }

        $this->assertStringContainsString("'source' => 'manual'", $seeder);
        $this->assertStringNotContainsString("'source' => 'mcp'", $seeder);
        $this->assertStringContainsString('externalCallsDisabled', $seeder);
    }

    public function testLocalRunbooksDescribeDemoEnvironment(): void
    {
        $devRunbook = $this->readFile(self::ROOT . '/docs/dev-runbook.md');
        $localReadme = $this->readFile(self::ROOT . '/deploy/local/README.md');
        $demoRunbook = $this->readFile(self::ROOT . '/docs/simple-stom-demo-runbook.md');
        $readme = $this->readFile(self::ROOT . '/README.md');
        $plan = $this->readFile(self::ROOT . '/docs/simple-stom-migration-plan.md');

        foreach ([$devRunbook, $localReadme, $demoRunbook] as $doc) {
            $this->assertStringContainsString('espo-dental-demo-seed', $doc);
            $this->assertStringContainsString('localhost:18080', $doc);
        }

        $this->assertStringContainsString('docs/simple-stom-demo-runbook.md', $readme);
        $this->assertStringContainsString('| 13. Demo environment | Completed |', $plan);
        $this->assertStringContainsString('Manual Demo Script', $demoRunbook);
        $this->assertStringContainsString('MCP and AI behavior stay', $demoRunbook);
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
