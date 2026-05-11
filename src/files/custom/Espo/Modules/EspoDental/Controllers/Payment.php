<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Controllers\Record;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\EspoDental\Services\PaymentService;

class Payment extends Record
{
    /**
     * POST /Payment/action/accept
     *
     * @return array{paymentId: string, number: string}
     */
    public function postActionAccept(Request $request): array
    {
        if (!$this->getAcl()->checkScope('Payment', 'create')) {
            throw new Forbidden();
        }
        $body = $request->getParsedBody();
        if (!is_object($body)) {
            throw new BadRequest('Invalid payload');
        }
        $data = [
            'patientId' => (string) ($body->patientId ?? ''),
            'invoiceId' => isset($body->invoiceId) ? (string) $body->invoiceId : null,
            'amount' => (float) ($body->amount ?? 0),
            'method' => isset($body->method) ? (string) $body->method : 'cash',
            'paidAt' => isset($body->paidAt) ? (string) $body->paidAt : null,
            'notes' => isset($body->notes) ? (string) $body->notes : null,
            'clinicId' => isset($body->clinicId) ? (string) $body->clinicId : null,
        ];
        /** @var PaymentService $service */
        $service = $this->injectableFactory->create(PaymentService::class);
        return $service->accept($data);
    }

    /**
     * POST /Payment/action/refund
     *
     * @return array{refundPaymentId: string}
     */
    public function postActionRefund(Request $request): array
    {
        if (!$this->getAcl()->checkScope('Payment', 'edit')) {
            throw new Forbidden();
        }
        $body = $request->getParsedBody();
        if (!is_object($body) || !isset($body->id)) {
            throw new BadRequest('id is required');
        }
        $id = (string) $body->id;
        $amount = isset($body->amount) ? (float) $body->amount : null;
        $reason = isset($body->reason) ? (string) $body->reason : '';
        /** @var PaymentService $service */
        $service = $this->injectableFactory->create(PaymentService::class);
        return $service->refund($id, $amount, $reason);
    }
}
