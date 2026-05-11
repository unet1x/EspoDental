<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\Appointment;
use Espo\Modules\EspoDental\Entities\Cabinet;

class CalendarService
{
    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    /**
     * @return array{
     *     date: string,
     *     view: string,
     *     cabinets: list<array{id: string, name: string, clinicId: ?string, capacity: int}>,
     *     appointments: list<array{
     *         id: string, name: string, dateStart: string, dateEnd: string,
     *         cabinetId: ?string, doctorId: ?string, doctorName: ?string,
     *         parentName: ?string, status: string
     *     }>
     * }
     */
    public function getDayData(
        string $date,
        ?string $clinicId,
        string $view = 'day',
        ?string $cabinetId = null
    ): array {
        $start = new DateTimeImmutable($date . ' 00:00:00');
        $end = match ($view) {
            'week' => $start->modify('+7 days'),
            default => $start->modify('+1 day'),
        };

        $where = ['deleted' => false];
        if ($clinicId) {
            $where['clinicId'] = $clinicId;
        }
        if ($cabinetId) {
            $where['id'] = $cabinetId;
        }

        /** @var iterable<Cabinet> $cabinets */
        $cabinets = $this->entityManager
            ->getRDBRepository(Cabinet::ENTITY_TYPE)
            ->where($where)
            ->order('order', 'ASC')
            ->find();

        $cabinetData = [];
        foreach ($cabinets as $c) {
            $cabinetData[] = [
                'id' => $c->getId(),
                'name' => (string) $c->get('name'),
                'clinicId' => $c->get('clinicId'),
                'capacity' => (int) ($c->get('capacity') ?? 1),
            ];
        }

        $appointmentWhere = [
            'deleted' => false,
            'dateStart>=' => $start->format('Y-m-d H:i:s'),
            'dateStart<' => $end->format('Y-m-d H:i:s'),
        ];
        if ($clinicId) {
            $appointmentWhere['clinicId'] = $clinicId;
        }
        if ($cabinetId) {
            $appointmentWhere['cabinetId'] = $cabinetId;
        }

        /** @var iterable<Appointment> $appointments */
        $appointments = $this->entityManager
            ->getRDBRepository(Appointment::ENTITY_TYPE)
            ->where($appointmentWhere)
            ->order('dateStart', 'ASC')
            ->find();

        $appData = [];
        foreach ($appointments as $a) {
            $appData[] = [
                'id' => $a->getId(),
                'name' => (string) $a->get('name'),
                'dateStart' => (string) $a->getDateStart(),
                'dateEnd' => (string) $a->getDateEnd(),
                'cabinetId' => $a->get('cabinetId'),
                'doctorId' => $a->get('doctorId'),
                'doctorName' => $a->get('doctorName'),
                'parentName' => $a->get('parentName'),
                'status' => (string) $a->getStatus(),
            ];
        }

        return [
            'date' => $start->format('Y-m-d'),
            'view' => $view,
            'cabinets' => $cabinetData,
            'appointments' => $appData,
        ];
    }

    /**
     * Find free slots for a duration in working hours.
     *
     * @return list<array{
     *     start: string, end: string,
     *     cabinetId: string, cabinetName: string,
     *     doctorId: ?string
     * }>
     */
    public function findFreeSlots(
        string $dateFrom,
        string $dateTo,
        int $durationMinutes,
        ?string $clinicId = null,
        ?string $cabinetId = null,
        ?string $doctorId = null,
        int $workStartHour = 8,
        int $workEndHour = 21,
        int $stepMinutes = 15,
        int $limit = 50
    ): array {
        if ($durationMinutes <= 0) {
            throw new BadRequest('durationMinutes must be positive');
        }
        if ($workEndHour <= $workStartHour) {
            throw new BadRequest('workEndHour must be greater than workStartHour');
        }
        $from = new DateTimeImmutable($dateFrom . ' 00:00:00');
        $to = new DateTimeImmutable($dateTo . ' 23:59:59');
        if ($to < $from) {
            throw new BadRequest('dateTo must be on/after dateFrom');
        }

        $cabWhere = ['deleted' => false];
        if ($clinicId) {
            $cabWhere['clinicId'] = $clinicId;
        }
        if ($cabinetId) {
            $cabWhere['id'] = $cabinetId;
        }
        /** @var iterable<Cabinet> $cabinets */
        $cabinets = $this->entityManager
            ->getRDBRepository(Cabinet::ENTITY_TYPE)
            ->where($cabWhere)
            ->order('order', 'ASC')
            ->find();

        $appWhere = [
            'deleted' => false,
            'status' => Appointment::BLOCKING_STATUSES,
            'dateStart<' => $to->format('Y-m-d H:i:s'),
            'dateEnd>' => $from->format('Y-m-d H:i:s'),
        ];
        if ($clinicId) {
            $appWhere['clinicId'] = $clinicId;
        }
        if ($cabinetId) {
            $appWhere['cabinetId'] = $cabinetId;
        }
        if ($doctorId) {
            $appWhere['doctorId'] = $doctorId;
        }
        /** @var iterable<Appointment> $apps */
        $apps = $this->entityManager
            ->getRDBRepository(Appointment::ENTITY_TYPE)
            ->where($appWhere)
            ->find();

        $occByCabinet = [];
        $occByDoctor = [];
        foreach ($apps as $a) {
            $cId = (string) $a->get('cabinetId');
            $dId = (string) $a->get('doctorId');
            $s = (int) strtotime((string) $a->getDateStart());
            $e = (int) strtotime((string) $a->getDateEnd());
            if ($cId !== '') {
                $occByCabinet[$cId][] = [$s, $e];
            }
            if ($dId !== '') {
                $occByDoctor[$dId][] = [$s, $e];
            }
        }

        $slots = [];
        $stepSec = $stepMinutes * 60;
        $durSec = $durationMinutes * 60;
        $today = $from;
        $now = time();

        while ($today <= $to && count($slots) < $limit) {
            foreach ($cabinets as $c) {
                $cId = $c->getId();
                $cName = (string) $c->get('name');
                $dayStartTs = $today->setTime($workStartHour, 0)->getTimestamp();
                $dayEndTs = $today->setTime($workEndHour, 0)->getTimestamp();
                for ($t = $dayStartTs; $t + $durSec <= $dayEndTs; $t += $stepSec) {
                    if ($t < $now) {
                        continue;
                    }
                    if ($this->overlapsAny($t, $t + $durSec, $occByCabinet[$cId] ?? [])) {
                        continue;
                    }
                    if (
                        $doctorId
                        && $this->overlapsAny($t, $t + $durSec, $occByDoctor[$doctorId] ?? [])
                    ) {
                        continue;
                    }
                    $slots[] = [
                        'start' => date('Y-m-d H:i:s', $t),
                        'end' => date('Y-m-d H:i:s', $t + $durSec),
                        'cabinetId' => $cId,
                        'cabinetName' => $cName,
                        'doctorId' => $doctorId,
                    ];
                    if (count($slots) >= $limit) {
                        break 2;
                    }
                }
            }
            $today = $today->modify('+1 day');
        }
        return $slots;
    }

    /**
     * @param list<array{int, int}> $intervals
     */
    private function overlapsAny(int $start, int $end, array $intervals): bool
    {
        foreach ($intervals as $iv) {
            if ($start < $iv[1] && $end > $iv[0]) {
                return true;
            }
        }
        return false;
    }

    public function moveAppointment(
        string $id,
        string $dateStart,
        string $dateEnd,
        ?string $cabinetId = null,
        ?string $doctorId = null
    ): Appointment {
        if ($dateStart === '' || $dateEnd === '') {
            throw new BadRequest('dateStart and dateEnd required');
        }
        try {
            $startDt = new DateTimeImmutable($dateStart);
            $endDt = new DateTimeImmutable($dateEnd);
        } catch (\Exception $e) {
            throw new BadRequest('Invalid datetime: ' . $e->getMessage());
        }
        if ($endDt <= $startDt) {
            throw new BadRequest('dateEnd must be after dateStart');
        }

        /** @var ?Appointment $appointment */
        $appointment = $this->entityManager->getEntityById(Appointment::ENTITY_TYPE, $id);
        if (!$appointment) {
            throw new NotFound('Appointment not found');
        }

        $appointment->set('dateStart', $startDt->format('Y-m-d H:i:s'));
        $appointment->set('dateEnd', $endDt->format('Y-m-d H:i:s'));
        if ($cabinetId !== null && $cabinetId !== '') {
            $appointment->set('cabinetId', $cabinetId);
        }
        if ($doctorId !== null && $doctorId !== '') {
            $appointment->set('doctorId', $doctorId);
        }
        $this->entityManager->saveEntity($appointment);
        return $appointment;
    }
}
