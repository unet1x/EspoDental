<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Controllers\Record;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\EspoDental\Services\AppointmentService;

class Appointment extends Record
{
    /**
     * GET /EspoDental/Appointment/bookingCandidates?q=...
     *
     * @return list<array<string, mixed>>
     */
    public function getActionBookingCandidates(Request $request): array
    {
        if (!$this->getAcl()->checkScope('Appointment', 'create')) {
            throw new Forbidden();
        }

        /** @var AppointmentService $service */
        $service = $this->injectableFactory->create(AppointmentService::class);

        return $service->searchBookingCandidates(
            (string) ($request->getQueryParam('q') ?? ''),
            (int) ($request->getQueryParam('limit') ?? 10)
        );
    }

    /**
     * POST /EspoDental/Appointment/bookFromSlot
     *
     * @return array{appointmentId: string, parentType: string, parentId: string, createdPreliminaryPatient: bool}
     */
    public function postActionBookFromSlot(Request $request): array
    {
        if (!$this->getAcl()->checkScope('Appointment', 'create')) {
            throw new Forbidden();
        }

        $body = $request->getParsedBody();
        $data = is_object($body) ? get_object_vars($body) : [];

        if (
            empty($data['parentId']) &&
            !$this->getAcl()->checkScope('PreliminaryPatient', 'create')
        ) {
            throw new Forbidden();
        }

        /** @var AppointmentService $service */
        $service = $this->injectableFactory->create(AppointmentService::class);

        return $service->bookFromSlot($data);
    }

    /**
     * POST /Appointment/action/startVisit
     *
     * @return array{visitId: string, appointmentId: string}
     */
    public function postActionStartVisit(Request $request): array
    {
        $body = $request->getParsedBody();
        $id = is_object($body) ? ($body->id ?? null) : null;

        if (!$id || !is_string($id)) {
            throw new BadRequest('id is required');
        }

        if (!$this->getAcl()->checkScope('Appointment', 'edit')) {
            throw new Forbidden();
        }
        if (!$this->getAcl()->checkScope('Visit', 'create')) {
            throw new Forbidden();
        }

        /** @var AppointmentService $service */
        $service = $this->injectableFactory->create(AppointmentService::class);

        return $service->startVisit($id);
    }
}
