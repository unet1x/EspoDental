<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Controllers\Record;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\EspoDental\Entities\Payment;
use Espo\Modules\EspoDental\Services\SalaryService;
use stdClass;

class SalaryEntry extends Record
{
    /**
     * @return stdClass
     */
    public function postActionBuild(Request $request): stdClass
    {
        $this->assertAccess();
        $data = $request->getParsedBody();
        $userId = (string) ($data->userId ?? '');
        $periodFrom = (string) ($data->periodFrom ?? '');
        $periodTo = (string) ($data->periodTo ?? '');
        $profileId = isset($data->profileId) ? (string) $data->profileId : null;
        if ($userId === '' || $periodFrom === '' || $periodTo === '') {
            throw new BadRequest('userId, periodFrom and periodTo are required');
        }
        /** @var SalaryService $salaryService */
        $salaryService = $this->injectableFactory->create(SalaryService::class);

        $entry = $salaryService->buildEntry($userId, $periodFrom, $periodTo, $profileId);
        return (object) ['id' => $entry->getId(), 'total' => $entry->getTotalAmount()];
    }

    public function postActionApprove(Request $request): stdClass
    {
        $this->assertAccess();
        $id = (string) ($request->getParsedBody()->id ?? '');
        if ($id === '') {
            throw new BadRequest('id required');
        }
        /** @var SalaryService $salaryService */
        $salaryService = $this->injectableFactory->create(SalaryService::class);

        $entry = $salaryService->approveEntry($id, $this->user);
        return (object) ['id' => $entry->getId(), 'status' => $entry->getStatus()];
    }

    public function postActionPay(Request $request): stdClass
    {
        $this->assertAccess();
        $data = $request->getParsedBody();
        $id = (string) ($data->id ?? '');
        $method = (string) ($data->method ?? Payment::METHOD_CASH);
        if ($id === '') {
            throw new BadRequest('id required');
        }
        /** @var SalaryService $salaryService */
        $salaryService = $this->injectableFactory->create(SalaryService::class);

        $entry = $salaryService->payEntry($id, $method);
        return (object) [
            'id' => $entry->getId(),
            'status' => $entry->getStatus(),
            'paidPaymentId' => $entry->get('paidPaymentId'),
        ];
    }

    public function postActionCancel(Request $request): stdClass
    {
        $this->assertAccess();
        $id = (string) ($request->getParsedBody()->id ?? '');
        if ($id === '') {
            throw new BadRequest('id required');
        }
        /** @var SalaryService $salaryService */
        $salaryService = $this->injectableFactory->create(SalaryService::class);

        $entry = $salaryService->cancelEntry($id);
        return (object) ['id' => $entry->getId(), 'status' => $entry->getStatus()];
    }

    private function assertAccess(): void
    {
        if (!$this->user->isAdmin() && !$this->user->isRegular()) {
            throw new Forbidden();
        }
    }
}
