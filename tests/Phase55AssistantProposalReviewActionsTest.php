<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase55AssistantProposalReviewActionsTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';
    private const CLIENT_ROOT = self::ROOT . '/src/files/client/custom/modules/espo-dental/src';

    public function testReviewRoutesAndControllerKeepProposalReviewHumanBounded(): void
    {
        $routes = $this->readJson(self::MODULE_ROOT . '/Resources/routes.json');
        $routeMap = [];
        foreach ($routes as $route) {
            $routeMap[$route['route']] = $route;
        }

        foreach (
            [
                '/EspoDental/AssistantActionProposal/approve' => 'approve',
                '/EspoDental/AssistantActionProposal/reject' => 'reject',
            ] as $path => $action
        ) {
            $this->assertArrayHasKey($path, $routeMap);
            $this->assertSame('post', $routeMap[$path]['method']);
            $this->assertSame('AssistantActionProposal', $routeMap[$path]['params']['controller']);
            $this->assertSame($action, $routeMap[$path]['params']['action']);
        }

        $controller = $this->readFile(self::MODULE_ROOT . '/Controllers/AssistantActionProposal.php');

        $this->assertStringContainsString('postActionApprove', $controller);
        $this->assertStringContainsString('postActionReject', $controller);
        $this->assertStringContainsString("checkScope(ProposalEntity::ENTITY_TYPE, 'edit')", $controller);
        $this->assertStringContainsString('STATUS_PENDING_REVIEW', $controller);
        $this->assertStringContainsString('Only pending review assistant proposals can be reviewed', $controller);
        $this->assertStringContainsString('reviewedById', $controller);
        $this->assertStringContainsString('reviewedAt', $controller);
        $this->assertStringContainsString('saveEntity($proposal)', $controller);
        $this->assertStringNotContainsString('STATUS_APPLIED', $controller);
    }

    public function testRecordButtonsHandlerAndLocalesExposeApproveReject(): void
    {
        $clientDefs = $this->readJson(
            self::MODULE_ROOT . '/Resources/metadata/clientDefs/AssistantActionProposal.json'
        );
        $handler = $this->readFile(self::CLIENT_ROOT . '/handlers/assistant-action-proposal/review.js');

        $this->assertSame(
            'espo-dental:handlers/assistant-action-proposal/review',
            $clientDefs['menu']['detail']['buttons'][0]['handler']
        );
        $this->assertSame('approve', $clientDefs['menu']['detail']['buttons'][0]['name']);
        $this->assertSame('reject', $clientDefs['menu']['detail']['buttons'][1]['name']);
        $this->assertSame('edit', $clientDefs['menu']['detail']['buttons'][0]['acl']);
        $this->assertSame('edit', $clientDefs['menu']['detail']['buttons'][1]['acl']);

        foreach (
            [
                'actionApprove',
                'actionReject',
                'pending_review',
                'EspoDental/AssistantActionProposal/',
                'reviewNotes',
                'Only pending review proposals can be reviewed',
            ] as $needle
        ) {
            $this->assertStringContainsString($needle, $handler);
        }

        foreach (['ru_RU', 'en_US', 'es_ES'] as $locale) {
            $labels = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/AssistantActionProposal.json");

            $this->assertArrayHasKey('Approve', $labels['labels']);
            $this->assertArrayHasKey('Reject', $labels['labels']);
            $this->assertArrayHasKey('Review notes are optional', $labels['messages']);
            $this->assertArrayHasKey('Review failed', $labels['messages']);
        }
    }

    public function testIntegrationOpsCenterLinksToReviewAndNotificationRecords(): void
    {
        $view = $this->readFile(self::CLIENT_ROOT . '/views/dashlets/integration-ops-center.js');

        $this->assertStringContainsString("renderRecordLink('NotificationLog'", $view);
        $this->assertStringContainsString("renderRecordLink('AssistantActionProposal'", $view);
        $this->assertStringContainsString('rawColumns: [3]', $view);
        $this->assertStringContainsString('#\' + encodeURIComponent(entityType) + \'/view/\'', $view);
    }

    public function testDocsTrackHumanReviewShortcut(): void
    {
        foreach (
            [
                self::ROOT . '/docs/espo-dental-product-development-plan.md',
                self::ROOT . '/docs/current-state.md',
                self::ROOT . '/docs/integration-architecture.md',
                self::ROOT . '/docs/release-notes.md',
                self::ROOT . '/docs/simple-stom-demo-runbook.md',
            ] as $path
        ) {
            $doc = $this->readFile($path);

            $this->assertStringContainsString('AssistantActionProposal', $doc);
            $this->assertStringContainsString('approve/reject', $doc);
        }
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
