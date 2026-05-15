<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config;
use Espo\Modules\EspoDental\Entities\Appointment;
use Espo\Modules\EspoDental\Entities\Cabinet;

class CalendarService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Config $config
    ) {
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
        int $limit = 50,
        ?string $excludeAppointmentId = null,
        ?string $parentType = null,
        ?string $parentId = null
    ): array {
        if ($durationMinutes <= 0) {
            throw new BadRequest('durationMinutes must be positive');
        }
        if ($workEndHour <= $workStartHour) {
            throw new BadRequest('workEndHour must be greater than workStartHour');
        }
        $timeZone = $this->getTimeZone();
        $utc = new DateTimeZone('UTC');
        $fromLocal = new DateTimeImmutable($dateFrom . ' 00:00:00', $timeZone);
        $toLocal = new DateTimeImmutable($dateTo . ' 23:59:59', $timeZone);
        if ($toLocal < $fromLocal) {
            throw new BadRequest('dateTo must be on/after dateFrom');
        }
        $from = $fromLocal->setTimezone($utc);
        $to = $toLocal->setTimezone($utc);

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

        // Load all overlapping appointments for this date window. Cabinet
        // occupancy is keyed by cabinet id below, while doctor and patient
        // occupancy must stay global across cabinets and clinics.
        /** @var iterable<Appointment> $apps */
        $apps = $this->entityManager
            ->getRDBRepository(Appointment::ENTITY_TYPE)
            ->where($appWhere)
            ->find();

        $occByCabinet = [];
        $occByDoctor = [];
        $occByPatient = [];
        foreach ($apps as $a) {
            if ($excludeAppointmentId && $a->getId() === $excludeAppointmentId) {
                continue;
            }

            $cId = (string) $a->get('cabinetId');
            $dId = (string) $a->get('doctorId');
            $pKey = $this->patientKey($a->getParentType(), $a->getParentId());
            $s = (int) strtotime((string) $a->getDateStart());
            $e = (int) strtotime((string) $a->getDateEnd());
            if ($cId !== '') {
                $occByCabinet[$cId][] = [$s, $e];
            }
            if ($dId !== '') {
                $occByDoctor[$dId][] = [$s, $e];
            }
            if ($pKey !== null) {
                $occByPatient[$pKey][] = [$s, $e];
            }
        }

        $slots = [];
        $stepSec = $stepMinutes * 60;
        $durSec = $durationMinutes * 60;
        $today = $fromLocal;
        $lastDay = new DateTimeImmutable($dateTo . ' 00:00:00', $timeZone);
        $now = time();
        $requestedPatientKey = $this->patientKey($parentType, $parentId);

        while ($today <= $lastDay && count($slots) < $limit) {
            foreach ($cabinets as $c) {
                $cId = $c->getId();
                $cName = (string) $c->get('name');
                $dayStartTs = $today->setTime($workStartHour, 0)->setTimezone($utc)->getTimestamp();
                $dayEndTs = $today->setTime($workEndHour, 0)->setTimezone($utc)->getTimestamp();
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
                    if (
                        $requestedPatientKey
                        && $this->overlapsAny($t, $t + $durSec, $occByPatient[$requestedPatientKey] ?? [])
                    ) {
                        continue;
                    }
                    $slots[] = [
                        'start' => gmdate('Y-m-d H:i:s', $t),
                        'end' => gmdate('Y-m-d H:i:s', $t + $durSec),
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

    private function patientKey(?string $parentType, ?string $parentId): ?string
    {
        if (!$parentType || !$parentId) {
            return null;
        }

        return $parentType . ':' . $parentId;
    }

    private function getTimeZone(): DateTimeZone
    {
        $timeZone = (string) ($this->config->get('timeZone') ?: 'UTC');

        try {
            return new DateTimeZone($timeZone);
        } catch (\Exception) {
            return new DateTimeZone('UTC');
        }
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
