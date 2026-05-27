<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase62StageJAcceptanceSummaryTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental/src';

    public function testHealthcheckReturnsStageJAcceptanceSummary(): void
    {
        $service = $this->readFile(self::MODULE_ROOT . '/Services/IntegrationMcpService.php');

        foreach (
            [
                'buildStageJAcceptance',
                "'stageJAcceptance' => \$stageJAcceptance",
                'mcp_contract',
                'provider_readiness',
                'credential_gate',
                'queue_preflight',
                'notification_retry_loop',
                'proposal_review_loop',
                '/EspoDental/NotificationLog/processQueue',
                '/EspoDental/Integration/acceptProviderCredentials',
                'restrictedMcpRoutes',
                'ungatedLiveRows',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $service);
        }
    }

    public function testIntegrationOpsCenterRendersStageJAcceptancePanel(): void
    {
        $view = $this->readFile(self::CLIENT_ROOT . '/views/dashlets/integration-ops-center.js');

        foreach (
            [
                'stageJAcceptance',
                'renderStageJAcceptance',
                'Stage J acceptance',
                'Готово ',
                'Внимание ',
                'acceptance.checks',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $view);
        }
    }

    public function testDocsTrackStageJAcceptanceSummary(): void
    {
        foreach (
            [
                self::ROOT . '/docs/espo-dental-product-development-plan.md',
                self::ROOT . '/docs/current-state.md',
                self::ROOT . '/docs/integration-architecture.md',
                self::ROOT . '/docs/release-notes.md',
                self::ROOT . '/docs/simple-stom-demo-runbook.md',
                self::ROOT . '/docs/simple-stom-migration-plan.md',
            ] as $path
        ) {
            $doc = $this->readFile($path);

            $this->assertStringContainsString('Stage J acceptance', $doc);
            $this->assertStringContainsString('stageJAcceptance', $doc);
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
