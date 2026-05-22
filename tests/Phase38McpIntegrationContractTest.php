<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase38McpIntegrationContractTest extends TestCase
{
    private const MODULE_ROOT = __DIR__ . '/../src/files/custom/Espo/Modules/EspoDental';

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

    public function testRoutesExposeOnlyNarrowMcpContract(): void
    {
        $routes = $this->readJson(self::MODULE_ROOT . '/Resources/routes.json');
        $routeMap = [];
        foreach ($routes as $route) {
            $routeMap[$route['route']] = $route;
        }

        foreach ([
            '/EspoDental/Integration/tools' => 'get',
            '/EspoDental/Integration/patientContext' => 'get',
            '/EspoDental/Integration/proposeAction' => 'post',
        ] as $route => $method) {
            $this->assertArrayHasKey($route, $routeMap);
            $this->assertSame($method, $routeMap[$route]['method']);
            $this->assertSame('Integration', $routeMap[$route]['params']['controller']);
        }

        foreach (array_keys($routeMap) as $route) {
            $this->assertStringNotContainsString('/Integration/postPayment', $route);
            $this->assertStringNotContainsString('/Integration/finishVisit', $route);
            $this->assertStringNotContainsString('/Integration/cancelInvoice', $route);
            $this->assertStringNotContainsString('/Integration/editMedicalNote', $route);
        }
    }

    public function testIntegrationControllerChecksAuthenticationAclAndPayloads(): void
    {
        $controller = (string) file_get_contents(self::MODULE_ROOT . '/Controllers/Integration.php');

        $this->assertStringContainsString('getActionTools', $controller);
        $this->assertStringContainsString('getActionPatientContext', $controller);
        $this->assertStringContainsString('postActionProposeAction', $controller);
        $this->assertStringContainsString("checkScope('Patient', 'read')", $controller);
        $this->assertStringContainsString("checkScope('AssistantActionProposal', 'create')", $controller);
        $this->assertStringContainsString("checkScope('Invoice', 'read')", $controller);
        $this->assertStringContainsString("checkScope('Payment', 'read')", $controller);
        $this->assertStringContainsString('patientId is required', $controller);
        $this->assertStringContainsString('assertRegularUser', $controller);
    }

    public function testIntegrationServiceCreatesProposalsButNoDirectMutations(): void
    {
        $service = (string) file_get_contents(self::MODULE_ROOT . '/Services/IntegrationMcpService.php');

        $this->assertStringContainsString('patient_context.read', $service);
        $this->assertStringContainsString('assistant_action.propose', $service);
        $this->assertStringContainsString("'directMutation' => false", $service);
        $this->assertStringContainsString('blockedDirectMutations', $service);
        $this->assertStringContainsString('ACTION_POST_PAYMENT', $service);
        $this->assertStringContainsString('ACTION_FINISH_VISIT', $service);
        $this->assertStringContainsString('ACTION_EDIT_MEDICAL_NOTE', $service);
        $this->assertStringContainsString('ACTION_CANCEL_INVOICE', $service);
        $this->assertStringContainsString('STATUS_PENDING_REVIEW', $service);
        $this->assertStringContainsString("set('requiresApproval', true)", $service);
        $this->assertStringNotContainsString('PaymentService', $service);
        $this->assertStringNotContainsString('VisitService', $service);
        $this->assertStringNotContainsString('InvoiceService', $service);
    }

    public function testMcpDesignDocumentationMatchesImplementedRoutes(): void
    {
        $design = (string) file_get_contents(__DIR__ . '/../docs/mcp-server-design.md');
        $architecture = (string) file_get_contents(__DIR__ . '/../docs/integration-architecture.md');
        $current = (string) file_get_contents(__DIR__ . '/../docs/current-state.md');
        $release = (string) file_get_contents(__DIR__ . '/../docs/release-notes.md');

        foreach ([
            '/EspoDental/Integration/tools',
            '/EspoDental/Integration/patientContext',
            '/EspoDental/Integration/proposeAction',
        ] as $route) {
            $this->assertStringContainsString($route, $design);
            $this->assertStringContainsString($route, $architecture);
        }

        $this->assertStringContainsString('Never expose generic REST write access', $design);
        $this->assertStringContainsString('first CRM-side MCP contract', $current);
        $this->assertStringContainsString('CRM-side MCP contract', $release);
    }
}
