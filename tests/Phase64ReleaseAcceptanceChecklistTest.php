<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase64ReleaseAcceptanceChecklistTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const CHECKLIST = self::ROOT . '/docs/release-acceptance-checklist.md';

    public function testReleaseAcceptanceChecklistDefinesStageKGates(): void
    {
        $doc = $this->readFile(self::CHECKLIST);

        foreach (
            [
                'Automated Gate',
                'Browser Workspace Gate',
                'API Gate',
                'Install And Recovery Gate',
                'Evidence',
                'bash deploy/local/release-readiness-smoke.sh',
                'stageJAcceptance',
                'GET /EspoDental/Integration/healthcheck?limit=2',
                'GET /EspoDental/Report/managementSnapshot?limit=2',
                'GET /EspoDental/Report/export?source=finance&format=json&limit=2',
                'GET /EspoDental/Inventory/workspace?limit=2',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $doc);
        }
    }

    public function testReleaseAcceptanceChecklistKeepsRiskyActionsManual(): void
    {
        $doc = $this->readFile(self::CHECKLIST);

        foreach (
            [
                'live SMTP, Telegram or WhatsApp sends',
                'NotificationLog/processQueue',
                'Integration/acceptProviderCredentials',
                'payment posting',
                'visit finish',
                'inventory receipt, transfer, write-off or adjustment',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $doc);
        }
    }

    public function testPrimaryDocsLinkReleaseAcceptanceChecklist(): void
    {
        foreach (
            [
                self::ROOT . '/docs/dev-runbook.md',
                self::ROOT . '/docs/simple-stom-demo-runbook.md',
                self::ROOT . '/docs/espo-dental-product-development-plan.md',
                self::ROOT . '/docs/current-state.md',
                self::ROOT . '/docs/release-notes.md',
                self::ROOT . '/docs/acceptance-checklist.md',
                self::ROOT . '/docs/simple-stom-migration-plan.md',
            ] as $path
        ) {
            $doc = $this->readFile($path);

            $this->assertStringContainsString('docs/release-acceptance-checklist.md', $doc);
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
