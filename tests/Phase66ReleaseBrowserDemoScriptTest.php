<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase66ReleaseBrowserDemoScriptTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const SCRIPT = self::ROOT . '/docs/release-browser-demo-script.md';

    public function testBrowserDemoScriptCoversReleaseWorkspaces(): void
    {
        $doc = $this->readFile(self::SCRIPT);

        foreach (
            [
                'Dashboard Entry',
                'Calendar And Booking',
                'Patient Workspace',
                'Cash Desk',
                'Inventory',
                'Management And Reports',
                'Integration Operations',
                'stageJAcceptance',
                'queuedReadyCount',
                'queuedBlockedCount',
                'Смирнов Алексей',
                'EspoDental/Report/export',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $doc);
        }
    }

    public function testBrowserDemoScriptKeepsRiskyActionsCancelledOrManual(): void
    {
        $doc = $this->readFile(self::SCRIPT);

        foreach (
            [
                'close the modal without saving',
                'cancel the wizard without posting payment',
                'close every dialog without saving',
                'Do not run provider sends',
                'processQueue',
                'provider credential acceptance',
                'visit finish',
                'inventory write actions',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $doc);
        }
    }

    public function testPrimaryDocsLinkBrowserDemoScript(): void
    {
        foreach (
            [
                self::ROOT . '/docs/dev-runbook.md',
                self::ROOT . '/docs/simple-stom-demo-runbook.md',
                self::ROOT . '/docs/release-acceptance-checklist.md',
                self::ROOT . '/docs/espo-dental-product-development-plan.md',
                self::ROOT . '/docs/current-state.md',
                self::ROOT . '/docs/release-notes.md',
                self::ROOT . '/docs/simple-stom-migration-plan.md',
            ] as $path
        ) {
            $doc = $this->readFile($path);

            $this->assertStringContainsString('docs/release-browser-demo-script.md', $doc);
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
