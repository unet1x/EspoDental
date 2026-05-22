<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase40VirtualAdministratorDesignTest extends TestCase
{
    private const DESIGN = __DIR__ . '/../docs/virtual-administrator-design.md';

    public function testDesignDefinesLocalDeploymentAndAllowedTools(): void
    {
        $doc = $this->readDesign();

        foreach ([
            'Proxmox VM',
            'trusted LAN host',
            'no patient data sent to public SaaS LLM APIs by default',
            'GET /EspoDental/Integration/tools',
            'GET /EspoDental/Integration/patientContext',
            'POST /EspoDental/Integration/proposeAction',
            'dedicated low-privilege integration user',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }
    }

    public function testDesignBlocksCriticalDirectMutations(): void
    {
        $doc = $this->readDesign();

        foreach ([
            'create or modify payments',
            'finish visits',
            'edit medical notes',
            'cancel or storno invoices',
            'delete records',
            'change stock movement records',
            'bypass questionnaire completion rules',
            'create patients outside the preliminary-patient conversion workflow',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }
    }

    public function testDesignRequiresProposalAuditAndPromptGuardrails(): void
    {
        $doc = $this->readDesign();

        foreach ([
            'AssistantActionProposal',
            'source `llm`',
            'NotificationLog',
            'Never post payments, finish visits, edit medical notes, cancel invoices or delete records.',
            'create an AssistantActionProposal and tell the user the proposal id',
            'Every state-changing suggestion must have an `AssistantActionProposal`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }
    }

    public function testRelatedDocsLinkVirtualAdministratorDesign(): void
    {
        $readme = (string) file_get_contents(__DIR__ . '/../README.md');
        $architecture = (string) file_get_contents(__DIR__ . '/../docs/integration-architecture.md');
        $current = (string) file_get_contents(__DIR__ . '/../docs/current-state.md');
        $release = (string) file_get_contents(__DIR__ . '/../docs/release-notes.md');
        $checklist = (string) file_get_contents(__DIR__ . '/../docs/acceptance-checklist.md');

        $this->assertStringContainsString('docs/virtual-administrator-design.md', $readme);
        $this->assertStringContainsString('docs/virtual-administrator-design.md', $architecture);
        $this->assertStringContainsString('local LLM virtual administrator design', $current);
        $this->assertStringContainsString('virtual-administrator-design.md', $release);
        $this->assertStringContainsString('Local LLM virtual administrator uses only', $checklist);
    }

    private function readDesign(): string
    {
        $this->assertFileExists(self::DESIGN);

        return (string) file_get_contents(self::DESIGN);
    }
}
