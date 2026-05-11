<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Controllers;

use DateTimeImmutable;
use Espo\Core\Api\Request;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Entities\User;
use Espo\Modules\EspoDental\Services\CalendarService;
use stdClass;

class Calendar
{
    public function __construct(
        private readonly CalendarService $calendarService,
        private readonly User $user
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getActionAppointments(Request $request): array
    {
        $this->assertAccess();
        $date = (string) ($request->getQueryParam('date') ?? (new DateTimeImmutable('today'))->format('Y-m-d'));
        $view = (string) ($request->getQueryParam('view') ?? 'day');
        $clinicId = $request->getQueryParam('clinicId');
        if (!in_array($view, ['day', 'week'], true)) {
            $view = 'day';
        }
        return $this->calendarService->getDayData(
            $date,
            $clinicId !== null && $clinicId !== '' ? (string) $clinicId : null,
            $view
        );
    }

    public function postActionMove(Request $request): stdClass
    {
        $this->assertAccess();
        $data = $request->getParsedBody();
        $id = (string) ($data->id ?? '');
        $dateStart = (string) ($data->dateStart ?? '');
        $dateEnd = (string) ($data->dateEnd ?? '');
        $cabinetId = isset($data->cabinetId) ? (string) $data->cabinetId : null;
        $doctorId = isset($data->doctorId) ? (string) $data->doctorId : null;
        if ($id === '') {
            throw new BadRequest('id required');
        }
        $appointment = $this->calendarService->moveAppointment(
            $id,
            $dateStart,
            $dateEnd,
            $cabinetId,
            $doctorId
        );
        return (object) [
            'id' => $appointment->getId(),
            'dateStart' => $appointment->getDateStart(),
            'dateEnd' => $appointment->getDateEnd(),
            'cabinetId' => $appointment->get('cabinetId'),
            'status' => $appointment->getStatus(),
        ];
    }

    private function assertAccess(): void
    {
        if (!$this->user->isAdmin() && !$this->user->isRegular()) {
            throw new Forbidden();
        }
    }
}
