<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase58IntegrationProviderReadinessTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental/src';

    public function testHealthcheckBuildsProviderReadinessChecklistWithoutSecrets(): void
    {
        $service = $this->readFile(self::MODULE_ROOT . '/Services/IntegrationMcpService.php');

        foreach (
            [
                'Config $config',
                'buildProviderChecklist',
                'buildLiveDeliveryReadiness',
                'runtimeRequirements',
                'configValuePresent',
                'provider_acceptance',
                'pending_acceptance',
                "'liveTestAllowed' => false",
                'runtimeConfigured',
                'runtimeMissingCount',
                'dryRunReadyCount',
                'acceptancePendingCount',
                'blockedLiveTestCount',
                "'sensitive' => true",
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $service);
        }

        foreach (
            [
                'espoDentalSmtpHost',
                'espoDentalTelegramBotToken',
                'espoDentalWhatsAppAccessToken',
            ] as $configKey
        ) {
            $this->assertStringContainsString($configKey, $service);
        }

        $this->assertStringNotContainsString('secretValue', $service);
    }

    public function testIntegrationOpsCenterRendersProviderReadinessChecklist(): void
    {
        $view = $this->readFile(self::CLIENT_ROOT . '/views/dashlets/integration-ops-center.js');
        $ui = $this->readFile(self::CLIENT_ROOT . '/lib/simple-stom-ui.js');

        foreach (
            [
                'renderProviderReadiness',
                'Provider readiness',
                'row.checklist',
                'row.liveDelivery',
                'runtimeMissingCount',
                'dryRunReadyCount',
                'acceptancePendingCount',
                'liveDelivery.nextStep',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $view);
        }

        foreach (['pending_acceptance', 'not_checked', 'blocked', 'missing'] as $status) {
            $this->assertStringContainsString($status, $ui);
        }
    }

    public function testDocsTrackProviderReadinessChecklist(): void
    {
        foreach (
            [
                self::ROOT . '/docs/espo-dental-product-development-plan.md',
                self::ROOT . '/docs/current-state.md',
                self::ROOT . '/docs/integration-architecture.md',
                self::ROOT . '/docs/release-notes.md',
                self::ROOT . '/docs/simple-stom-demo-runbook.md',
                self::ROOT . '/docs/simple-stom-migration-plan.md',
                self::ROOT . '/docs/mcp-server-design.md',
            ] as $path
        ) {
            $doc = $this->readFile($path);

            $this->assertStringContainsString('provider readiness checklist', $doc);
            $this->assertStringContainsString('pending_acceptance', $doc);
        }
    }

    private function readFile(string $path): string
    {
        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        $this->assertNotFalse($contents);

        return $contents;
    }
}
