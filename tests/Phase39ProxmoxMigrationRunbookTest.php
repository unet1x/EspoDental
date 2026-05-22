<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase39ProxmoxMigrationRunbookTest extends TestCase
{
    private const RUNBOOK = __DIR__ . '/../docs/proxmox-vm-migration.md';

    public function testRunbookDocumentsTargetTopologyAndDataLayout(): void
    {
        $doc = $this->readRunbook();

        foreach ([
            'AOOSTAR WTR MAX',
            'Proxmox VE',
            'Docker Compose',
            '/srv/espodental',
            'module/',
            'data/',
            'db/',
            'backups/',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }
    }

    public function testRunbookIncludesSynologyBackupAndVmRestoreCommands(): void
    {
        $doc = $this->readRunbook();

        foreach ([
            'mariadb-dump',
            '--single-transaction',
            '--routines',
            '--triggers',
            '--events',
            'rsync -aH --delete',
            'git -C /volume1/espomodule rev-parse HEAD',
            'gunzip -c',
            'docker compose exec -T db mariadb',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }
    }

    public function testRunbookIncludesPostRestoreVerificationAndRollback(): void
    {
        $doc = $this->readRunbook();

        foreach ([
            'php rebuild.php',
            'php command.php espo-dental-bootstrap',
            'php command.php update-app-timestamp',
            'curl -fsS',
            'open patient list',
            'open resource calendar',
            'Rollback',
            'Never promote an unverified staging restore directly over production',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }
    }

    public function testReadmeCurrentStateReleaseNotesAndChecklistLinkRunbook(): void
    {
        $readme = (string) file_get_contents(__DIR__ . '/../README.md');
        $current = (string) file_get_contents(__DIR__ . '/../docs/current-state.md');
        $release = (string) file_get_contents(__DIR__ . '/../docs/release-notes.md');
        $checklist = (string) file_get_contents(__DIR__ . '/../docs/acceptance-checklist.md');

        $this->assertStringContainsString('docs/proxmox-vm-migration.md', $readme);
        $this->assertStringContainsString('docs/proxmox-vm-migration.md', $current);
        $this->assertStringContainsString('AOOSTAR WTR MAX / Proxmox VM', $release);
        $this->assertStringContainsString('Proxmox VM restore runbook', $checklist);
    }

    private function readRunbook(): string
    {
        $this->assertFileExists(self::RUNBOOK);

        return (string) file_get_contents(self::RUNBOOK);
    }
}
