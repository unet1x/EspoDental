<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase63ReleaseReadinessSmokeTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const SCRIPT = self::ROOT . '/deploy/local/release-readiness-smoke.sh';

    public function testReleaseReadinessSmokeScriptCoversSafeApiSurfaces(): void
    {
        $script = $this->readFile(self::SCRIPT);

        $this->assertTrue(is_executable(self::SCRIPT), 'release-readiness smoke must be executable');

        foreach (
            [
                'EspoDental/Integration/healthcheck?limit=2',
                'stageJAcceptance',
                'directMutationToolCount',
                'restrictedMcpRoutes=0',
                'EspoDental/Report/managementSnapshot?limit=2',
                'EspoDental/Report/export?source=finance&format=json&limit=2',
                'EspoDental/Inventory/workspace?limit=2',
                'espo-dental-bootstrap',
                'espo-dental-demo-seed',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $script);
        }
    }

    public function testReleaseReadinessSmokeDoesNotCallRiskyActions(): void
    {
        $script = $this->readFile(self::SCRIPT);

        foreach (
            [
                '-X POST',
                'NotificationLog/processQueue',
                'Integration/acceptProviderCredentials',
                'AssistantActionProposal/approve',
                'AssistantActionProposal/reject',
                'Inventory/receipt',
                'Payment/action/accept',
            ] as $needle
        ) {
            $this->assertStringNotContainsString($needle, $script);
        }
    }

    public function testDocsTrackReleaseReadinessSmoke(): void
    {
        foreach (
            [
                self::ROOT . '/docs/dev-runbook.md',
                self::ROOT . '/docs/simple-stom-demo-runbook.md',
                self::ROOT . '/docs/espo-dental-product-development-plan.md',
                self::ROOT . '/docs/current-state.md',
                self::ROOT . '/docs/release-notes.md',
                self::ROOT . '/docs/acceptance-checklist.md',
                self::ROOT . '/deploy/local/README.md',
            ] as $path
        ) {
            $doc = $this->readFile($path);

            $this->assertStringContainsString('release-readiness-smoke.sh', $doc);
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
