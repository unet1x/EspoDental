<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Controllers\Record;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\EspoDental\Services\DoctorShiftTemplateService;

class DoctorShiftTemplate extends Record
{
    /**
     * @return array{templateId: string, created: int, skipped: int}
     */
    public function postActionGenerate(Request $request): array
    {
        if (!$this->getAcl()->checkScope('DoctorShiftTemplate', 'edit')) {
            throw new Forbidden();
        }

        $body = $request->getParsedBody();
        $id = is_object($body) ? ($body->id ?? null) : null;

        if (!$id || !is_string($id)) {
            throw new BadRequest('id is required');
        }

        /** @var DoctorShiftTemplateService $service */
        $service = $this->injectableFactory->create(DoctorShiftTemplateService::class);

        return $service->generate($id);
    }
}
