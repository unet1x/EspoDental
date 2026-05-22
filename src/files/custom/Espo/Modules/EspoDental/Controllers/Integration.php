<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Controllers\Record;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\EspoDental\Services\IntegrationMcpService;

class Integration extends Record
{
    /**
     * GET /EspoDental/Integration/tools
     *
     * @return array{tools: list<array<string, mixed>>}
     */
    public function getActionTools(Request $request): array
    {
        $this->assertRegularUser();

        /** @var IntegrationMcpService $service */
        $service = $this->injectableFactory->create(IntegrationMcpService::class);

        return ['tools' => $service->listTools()];
    }

    /**
     * GET /EspoDental/Integration/patientContext?patientId=...
     *
     * @return array<string, mixed>
     */
    public function getActionPatientContext(Request $request): array
    {
        $this->assertRegularUser();

        if (!$this->getAcl()->checkScope('Patient', 'read')) {
            throw new Forbidden();
        }

        $patientId = $request->getQueryParam('patientId');
        if (!$patientId || !is_string($patientId)) {
            throw new BadRequest('patientId is required');
        }

        $includeFinancials = (string) ($request->getQueryParam('includeFinancials') ?? '') === '1'
            && $this->getAcl()->checkScope('Invoice', 'read')
            && $this->getAcl()->checkScope('Payment', 'read');

        /** @var IntegrationMcpService $service */
        $service = $this->injectableFactory->create(IntegrationMcpService::class);

        return $service->getPatientContext($patientId, $includeFinancials);
    }

    /**
     * POST /EspoDental/Integration/proposeAction
     *
     * @return array{id: string, status: string, actionType: string, riskLevel: string}
     */
    public function postActionProposeAction(Request $request): array
    {
        $this->assertRegularUser();

        if (!$this->getAcl()->checkScope('AssistantActionProposal', 'create')) {
            throw new Forbidden();
        }

        $body = $request->getParsedBody();
        if (!is_object($body)) {
            throw new BadRequest('Invalid payload');
        }

        /** @var IntegrationMcpService $service */
        $service = $this->injectableFactory->create(IntegrationMcpService::class);

        return $service->createActionProposal((array) $body);
    }

    private function assertRegularUser(): void
    {
        $user = $this->getUser();
        if (!$user->isAdmin() && !$user->isRegular()) {
            throw new Forbidden();
        }
    }
}
