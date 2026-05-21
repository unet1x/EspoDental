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
use Espo\Modules\EspoDental\Entities\Clinic;

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
     *     timezone: string,
     *     cabinets: list<array{id: string, name: string, clinicId: ?string, capacity: int}>,
     *     appointments: list<array{
     *         id: string, name: string, dateStart: string, dateEnd: string,
     *         localStart: string, localEnd: string, timezone: string,
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
        $timeZone = $this->resolveTimeZone($clinicId);
        $utc = new DateTimeZone('UTC');
        $startLocal = new DateTimeImmutable($date . ' 00:00:00', $timeZone);
        $endLocal = match ($view) {
            'week' => $startLocal->modify('+7 days'),
            default => $startLocal->modify('+1 day'),
        };
        $startUtc = $startLocal->setTimezone($utc);
        $endUtc = $endLocal->setTimezone($utc);

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
            'dateStart<' => $endUtc->format('Y-m-d H:i:s'),
            'dateEnd>' => $startUtc->format('Y-m-d H:i:s'),
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
            $appointmentTimeZone = $this->resolveTimeZone((string) ($a->getClinicId() ?: $clinicId));
            $appointmentStartUtc = $this->parseUtcDateTime((string) $a->getDateStart());
            $appointmentEndUtc = $this->parseUtcDateTime((string) $a->getDateEnd());
            $localStart = $appointmentStartUtc
                ? $appointmentStartUtc->setTimezone($appointmentTimeZone)->format('Y-m-d H:i:s')
                : '';
            $localEnd = $appointmentEndUtc
                ? $appointmentEndUtc->setTimezone($appointmentTimeZone)->format('Y-m-d H:i:s')
                : '';

            $appData[] = [
                'id' => $a->getId(),
                'name' => (string) $a->get('name'),
                'dateStart' => (string) $a->getDateStart(),
                'dateEnd' => (string) $a->getDateEnd(),
                'localStart' => $localStart,
                'localEnd' => $localEnd,
                'timezone' => $appointmentTimeZone->getName(),
                'cabinetId' => $a->get('cabinetId'),
                'doctorId' => $a->get('doctorId'),
                'doctorName' => $a->get('doctorName'),
                'parentName' => $a->get('parentName'),
                'status' => (string) $a->getStatus(),
            ];
        }

        return [
            'date' => $startLocal->format('Y-m-d'),
            'view' => $view,
            'timezone' => $timeZone->getName(),
            'cabinets' => $cabinetData,
            'appointments' => $appData,
        ];
    }

    /**
     * Find free slots for a duration in working hours.
     *
     * @return list<array{
     *     start: string, end: string, localStart: string, localEnd: string, timezone: string,
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
        $timeZone = $this->resolveTimeZone($clinicId);
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
                    $startUtc = (new DateTimeImmutable('@' . $t))->setTimezone($utc);
                    $endUtc = (new DateTimeImmutable('@' . ($t + $durSec)))->setTimezone($utc);
                    $startLocal = $startUtc->setTimezone($timeZone);
                    $endLocal = $endUtc->setTimezone($timeZone);

                    $slots[] = [
                        'start' => $startUtc->format('Y-m-d H:i:s'),
                        'end' => $endUtc->format('Y-m-d H:i:s'),
                        'localStart' => $startLocal->format('Y-m-d H:i:s'),
                        'localEnd' => $endLocal->format('Y-m-d H:i:s'),
                        'timezone' => $timeZone->getName(),
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

    private function resolveTimeZone(?string $clinicId = null): DateTimeZone
    {
        if ($clinicId) {
            /** @var Clinic|null $clinic */
            $clinic = $this->entityManager->getEntityById(Clinic::ENTITY_TYPE, $clinicId);

            if ($clinic) {
                $timeZone = (string) ($clinic->get('timezone') ?: '');

                if ($timeZone !== '') {
                    return $this->buildTimeZone($timeZone);
                }
            }
        }

        return $this->buildTimeZone((string) ($this->config->get('timeZone') ?: 'UTC'));
    }

    private function buildTimeZone(string $timeZone): DateTimeZone
    {
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
        string $dateStart = '',
        string $dateEnd = '',
        ?string $cabinetId = null,
        ?string $doctorId = null,
        ?string $localStart = null,
        ?string $localEnd = null,
        ?string $timeZone = null
    ): Appointment {
        /** @var ?Appointment $appointment */
        $appointment = $this->entityManager->getEntityById(Appointment::ENTITY_TYPE, $id);
        if (!$appointment) {
            throw new NotFound('Appointment not found');
        }

        [$startDt, $endDt] = $this->resolveMoveDateTimes(
            $appointment,
            $dateStart,
            $dateEnd,
            $localStart,
            $localEnd,
            $timeZone
        );

        if ($endDt <= $startDt) {
            throw new BadRequest('dateEnd must be after dateStart');
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

    /**
     * @return array{DateTimeImmutable, DateTimeImmutable}
     */
    private function resolveMoveDateTimes(
        Appointment $appointment,
        string $dateStart,
        string $dateEnd,
        ?string $localStart,
        ?string $localEnd,
        ?string $timeZone
    ): array {
        $hasLocalStart = $localStart !== null && trim($localStart) !== '';
        $hasLocalEnd = $localEnd !== null && trim($localEnd) !== '';

        if ($hasLocalStart || $hasLocalEnd) {
            if (!$hasLocalStart || !$hasLocalEnd) {
                throw new BadRequest('localStart and localEnd required');
            }

            $zone = $timeZone !== null && trim($timeZone) !== ''
                ? $this->buildTimeZone((string) $timeZone)
                : $this->resolveTimeZone($appointment->getClinicId());

            return [
                $this->parseClinicLocalDateTime((string) $localStart, $zone),
                $this->parseClinicLocalDateTime((string) $localEnd, $zone),
            ];
        }

        if ($dateStart === '' || $dateEnd === '') {
            throw new BadRequest('dateStart and dateEnd required');
        }

        return [
            $this->parseRequiredUtcDateTime($dateStart),
            $this->parseRequiredUtcDateTime($dateEnd),
        ];
    }

    private function parseClinicLocalDateTime(string $value, DateTimeZone $timeZone): DateTimeImmutable
    {
        try {
            return (new DateTimeImmutable($value, $timeZone))->setTimezone(new DateTimeZone('UTC'));
        } catch (\Exception $e) {
            throw new BadRequest('Invalid local datetime: ' . $e->getMessage());
        }
    }

    private function parseRequiredUtcDateTime(string $value): DateTimeImmutable
    {
        try {
            return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));
        } catch (\Exception $e) {
            throw new BadRequest('Invalid datetime: ' . $e->getMessage());
        }
    }

    private function parseUtcDateTime(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }
}
