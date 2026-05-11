<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Controllers\Record;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\EspoDental\Services\VisitService;

class Visit extends Record
{
    /**
     * POST /Visit/action/finishVisit
     *
     * @return array{visitId: string, total: float, lineCount: int, invoiceId: ?string}
     */
    public function postActionFinishVisit(Request $request): array
    {
        $body = $request->getParsedBody();
        $id = is_object($body) ? ($body->id ?? null) : null;

        if (!$id || !is_string($id)) {
            throw new BadRequest('id is required');
        }

        if (!$this->getAcl()->checkScope('Visit', 'edit')) {
            throw new Forbidden();
        }

        /** @var VisitService $service */
        $service = $this->injectableFactory->create(VisitService::class);

        return $service->finishVisit($id);
    }
}
