<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase37AssistantActionProposalTest extends TestCase
{
    private const MODULE_ROOT = __DIR__ . '/../src/files/custom/Espo/Modules/EspoDental';
    private const LOCALES = ['ru_RU', 'en_US', 'es_ES'];

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        $this->assertFileExists($path);
        $data = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($data, "Invalid JSON: $path");

        return $data;
    }

    public function testAssistantProposalScopeMetadataAndLayoutsExist(): void
    {
        $scope = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/scopes/AssistantActionProposal.json');
        $this->assertTrue($scope['entity']);
        $this->assertTrue($scope['tab']);

        foreach (['detail', 'list', 'filters'] as $layout) {
            $this->assertFileExists(self::MODULE_ROOT . "/Resources/layouts/AssistantActionProposal/{$layout}.json");
        }

        $client = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/clientDefs/AssistantActionProposal.json');
        $this->assertContains('pendingReview', $client['boolFilterList']);
        $this->assertContains('highRisk', $client['boolFilterList']);
    }

    public function testAssistantProposalEntityDefinesRiskReviewAndTargetPayload(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/AssistantActionProposal.json');

        foreach ([
            'source',
            'actionType',
            'riskLevel',
            'status',
            'requiresApproval',
            'patient',
            'appointment',
            'notificationLog',
            'targetType',
            'targetId',
            'summary',
            'payload',
            'reviewNotes',
            'reviewedAt',
            'reviewedBy',
            'appliedAt',
        ] as $field) {
            $this->assertArrayHasKey($field, $def['fields']);
        }

        foreach (['mcp', 'llm', 'manual', 'system'] as $source) {
            $this->assertContains($source, $def['fields']['source']['options']);
        }
        foreach (['post_payment', 'finish_visit', 'edit_medical_note', 'cancel_invoice'] as $action) {
            $this->assertContains($action, $def['fields']['actionType']['options']);
        }
        foreach (['pending_review', 'approved', 'rejected', 'applied'] as $status) {
            $this->assertContains($status, $def['fields']['status']['options']);
        }

        $this->assertSame('hasMany', $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Patient.json')
            ['links']['assistantActionProposals']['type']);
        $this->assertSame('hasMany', $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Appointment.json')
            ['links']['assistantActionProposals']['type']);
    }

    public function testWorkflowGuardRequiresApprovalBeforeAppliedStatus(): void
    {
        $hook = (string) file_get_contents(
            self::MODULE_ROOT . '/Hooks/AssistantActionProposal/GuardWorkflow.php'
        );

        $this->assertStringContainsString('criticalActionTypes', $hook);
        $this->assertStringContainsString('ACTION_POST_PAYMENT', $hook);
        $this->assertStringContainsString('ACTION_FINISH_VISIT', $hook);
        $this->assertStringContainsString('ACTION_EDIT_MEDICAL_NOTE', $hook);
        $this->assertStringContainsString('ACTION_CANCEL_INVOICE', $hook);
        $this->assertStringContainsString('STATUS_APPROVED', $hook);
        $this->assertStringContainsString('STATUS_APPLIED', $hook);
        $this->assertStringContainsString('must be approved before it can be applied', $hook);
    }

    public function testAclSeederIncludesAssistantProposalWithLimitedOperationalRoles(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Tools/Installer/RoleSeeder.php');

        $this->assertStringContainsString("'AssistantActionProposal'", $code);
        $this->assertStringContainsString(
            "'AssistantActionProposal' => \$row('yes', 'team', 'team', 'no', 'no')",
            $code
        );
        $this->assertStringContainsString(
            "'AssistantActionProposal' => \$row('no', 'team', 'no', 'no', 'no')",
            $code
        );
    }

    public function testLocalesAndDocumentationExposeProposalWorkflow(): void
    {
        foreach (self::LOCALES as $locale) {
            $global = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Global.json");
            $this->assertArrayHasKey('AssistantActionProposal', $global['scopeNames']);
            $this->assertArrayHasKey('AssistantActionProposal', $global['scopeNamesPlural']);

            $labels = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/AssistantActionProposal.json");
            $this->assertArrayHasKey('actionType', $labels['fields']);
            $this->assertArrayHasKey('pending_review', $labels['options']['status']);
        }

        $architecture = (string) file_get_contents(__DIR__ . '/../docs/integration-architecture.md');
        $current = (string) file_get_contents(__DIR__ . '/../docs/current-state.md');
        $release = (string) file_get_contents(__DIR__ . '/../docs/release-notes.md');

        $this->assertStringContainsString('AssistantActionProposal', $architecture);
        $this->assertStringContainsString('draft-and-review', $current);
        $this->assertStringContainsString('LLM/MCP draft-and-review workflow', $release);
    }
}
