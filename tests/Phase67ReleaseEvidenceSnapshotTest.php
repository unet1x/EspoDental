<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase67ReleaseEvidenceSnapshotTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';

    public function testReleaseChecklistRecordsCurrentAutomatedEvidence(): void
    {
        $doc = $this->readFile(self::ROOT . '/docs/release-acceptance-checklist.md');

        foreach (
            [
                'Current Stage K Evidence',
                'bash deploy/local/release-readiness-smoke.sh`: passed',
                'bash deploy/check-deploy-readiness.sh`: passed',
                'vendor/bin/phpunit tests --no-coverage`: passed with the full suite',
                'git diff --check`: passed',
                'browser workspace pass using `docs/release-browser-demo-script.md`',
                'live provider smoke only when a safe demo channel has real accepted credentials',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $doc);
        }
    }

    public function testPlanSaysOnlyManualReleaseEvidenceRemains(): void
    {
        $plan = $this->readFile(self::ROOT . '/docs/espo-dental-product-development-plan.md');

        foreach (
            [
                'Final manual release evidence',
                'No further automated code stage is queued',
                'Browser screenshots and live provider smoke remain',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $plan);
        }
    }

    public function testMigrationPlanTracksStageKEvidenceSnapshot(): void
    {
        $plan = $this->readFile(self::ROOT . '/docs/simple-stom-migration-plan.md');

        $this->assertStringContainsString('Pass 19 - Stage K Evidence Snapshot', $plan);
        $this->assertStringContainsString('release-readiness smoke, deploy-readiness check, full PHPUnit suite', $plan);
        $this->assertStringContainsString('manual release evidence', $plan);
    }

    private function readFile(string $path): string
    {
        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        $this->assertNotFalse($contents);

        return $contents;
    }
}
