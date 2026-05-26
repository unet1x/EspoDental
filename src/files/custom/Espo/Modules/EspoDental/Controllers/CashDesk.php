<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Entities\User;
use Espo\Modules\EspoDental\Services\CashDeskService;

class CashDesk
{
    public function __construct(
        private readonly CashDeskService $cashDeskService,
        private readonly User $user
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getActionWorkspace(Request $request): array
    {
        $this->assertAccess();

        return $this->cashDeskService->getWorkspace(
            $request->getQueryParam('clinicId') ? (string) $request->getQueryParam('clinicId') : null,
            $request->getQueryParam('doctorId') ? (string) $request->getQueryParam('doctorId') : null,
            $request->getQueryParam('unpaidOnly') !== 'false',
            (int) ($request->getQueryParam('limit') ?? 40),
            $request->getQueryParam('selectedInvoiceId') ? (string) $request->getQueryParam('selectedInvoiceId') : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function postActionCloseShift(Request $request): array
    {
        $this->assertAccess();
        $body = $request->getParsedBody();
        if (!is_object($body) || empty($body->clinicId)) {
            throw new BadRequest('clinicId is required');
        }

        return $this->cashDeskService->closeShift(
            (string) $body->clinicId,
            isset($body->periodFrom) ? (string) $body->periodFrom : null,
            isset($body->periodTo) ? (string) $body->periodTo : null
        );
    }

    private function assertAccess(): void
    {
        if (!$this->user->isAdmin() && !$this->user->isRegular()) {
            throw new Forbidden();
        }
    }
}
