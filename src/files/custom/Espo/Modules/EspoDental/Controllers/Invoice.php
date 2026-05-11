<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Controllers\Record;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\EspoDental\Services\InvoiceService;

class Invoice extends Record
{
    private function requireId(Request $request): string
    {
        $body = $request->getParsedBody();
        $id = is_object($body) ? ($body->id ?? null) : null;
        if (!$id || !is_string($id)) {
            throw new BadRequest('id is required');
        }
        return $id;
    }

    /**
     * @return array{invoiceId: string, number: string, total: float}
     */
    public function postActionIssue(Request $request): array
    {
        if (!$this->getAcl()->checkScope('Invoice', 'edit')) {
            throw new Forbidden();
        }
        /** @var InvoiceService $service */
        $service = $this->injectableFactory->create(InvoiceService::class);
        return $service->issue($this->requireId($request));
    }

    /**
     * @return array{stornoInvoiceId: string}
     */
    public function postActionStorno(Request $request): array
    {
        if (!$this->getAcl()->checkScope('Invoice', 'edit')) {
            throw new Forbidden();
        }
        $body = $request->getParsedBody();
        $reason = is_object($body) ? (string) ($body->reason ?? '') : '';
        /** @var InvoiceService $service */
        $service = $this->injectableFactory->create(InvoiceService::class);
        return $service->storno($this->requireId($request), $reason);
    }

    /**
     * @return array{attachmentId: string, name: string}
     */
    public function postActionBuildPdf(Request $request): array
    {
        if (!$this->getAcl()->checkScope('Invoice', 'read')) {
            throw new Forbidden();
        }
        /** @var InvoiceService $service */
        $service = $this->injectableFactory->create(InvoiceService::class);
        return $service->buildPdf($this->requireId($request));
    }
}
