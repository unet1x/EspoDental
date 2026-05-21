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
        $cabinetId = $request->getQueryParam('cabinetId');
        if (!in_array($view, ['day', 'week'], true)) {
            $view = 'day';
        }
        return $this->calendarService->getDayData(
            $date,
            $clinicId !== null && $clinicId !== '' ? (string) $clinicId : null,
            $view,
            $cabinetId !== null && $cabinetId !== '' ? (string) $cabinetId : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getActionFreeSlots(Request $request): array
    {
        $this->assertAccess();
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        $from = (string) ($request->getQueryParam('dateFrom') ?? $today);
        $to = (string) ($request->getQueryParam('dateTo') ?? $from);
        $duration = (int) ($request->getQueryParam('durationMinutes') ?? 30);
        $clinicId = $request->getQueryParam('clinicId');
        $cabinetId = $request->getQueryParam('cabinetId');
        $doctorId = $request->getQueryParam('doctorId');
        $parentType = $request->getQueryParam('parentType');
        $parentId = $request->getQueryParam('parentId');
        $excludeAppointmentId = $request->getQueryParam('excludeAppointmentId');
        $workStart = (int) ($request->getQueryParam('workStartHour') ?? 8);
        $workEnd = (int) ($request->getQueryParam('workEndHour') ?? 21);
        $step = (int) ($request->getQueryParam('stepMinutes') ?? 15);
        $limit = (int) ($request->getQueryParam('limit') ?? 50);
        if ($duration <= 0) {
            throw new BadRequest('durationMinutes must be positive');
        }
        $slots = $this->calendarService->findFreeSlots(
            $from,
            $to,
            $duration,
            $clinicId !== null && $clinicId !== '' ? (string) $clinicId : null,
            $cabinetId !== null && $cabinetId !== '' ? (string) $cabinetId : null,
            $doctorId !== null && $doctorId !== '' ? (string) $doctorId : null,
            $workStart,
            $workEnd,
            $step,
            $limit,
            $excludeAppointmentId !== null && $excludeAppointmentId !== '' ? (string) $excludeAppointmentId : null,
            $parentType !== null && $parentType !== '' ? (string) $parentType : null,
            $parentId !== null && $parentId !== '' ? (string) $parentId : null
        );
        return ['slots' => $slots, 'count' => count($slots)];
    }

    public function postActionMove(Request $request): stdClass
    {
        $this->assertAccess();
        $data = $request->getParsedBody();
        $id = (string) ($data->id ?? '');
        $dateStart = (string) ($data->dateStart ?? '');
        $dateEnd = (string) ($data->dateEnd ?? '');
        $localStart = isset($data->localStart) ? (string) $data->localStart : null;
        $localEnd = isset($data->localEnd) ? (string) $data->localEnd : null;
        $timeZone = isset($data->timezone) ? (string) $data->timezone : null;
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
            $doctorId,
            $localStart,
            $localEnd,
            $timeZone
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
