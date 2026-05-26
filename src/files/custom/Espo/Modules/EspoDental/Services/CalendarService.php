<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config;
use Espo\Entities\User;
use Espo\Modules\EspoDental\Entities\Appointment;
use Espo\Modules\EspoDental\Entities\Cabinet;
use Espo\Modules\EspoDental\Entities\Clinic;
use Espo\Modules\EspoDental\Entities\DoctorShift;
use Espo\Modules\EspoDental\Entities\Service;
use Espo\Modules\EspoDental\Tools\CabinetRequirementMatcher;
use Espo\Modules\EspoDental\Tools\DoctorShiftAvailability;

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
     *     doctors: list<array{id: string, name: string}>,
     *     filters: array{
     *         cabinets: list<array{id: string, name: string, clinicId: ?string, capacity: int}>,
     *         doctors: list<array{id: string, name: string}>,
     *         services: list<array{id: string, name: string, duration: int}>
     *     },
     *     appointments: list<array{
     *         id: string, name: string, dateStart: string, dateEnd: string,
     *         localStart: string, localEnd: string, timezone: string,
     *         cabinetId: ?string, doctorId: ?string, doctorName: ?string,
     *         parentName: string, patientName: string, status: string
     *     }>
     * }
     */
    public function getDayData(
        string $date,
        ?string $clinicId,
        string $view = 'day',
        ?string $cabinetId = null,
        ?string $doctorId = null,
        ?string $serviceId = null
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

        $service = $this->loadService($serviceId);
        /** @var iterable<Cabinet> $cabinets */
        $cabinets = $this->entityManager
            ->getRDBRepository(Cabinet::ENTITY_TYPE)
            ->where($where)
            ->order('order', 'ASC')
            ->find();
        $cabinets = $this->filterCabinetsByService($cabinets, $service);

        $cabinetData = [];
        foreach ($cabinets as $c) {
            $cabinetData[] = [
                'id' => $c->getId(),
                'name' => (string) $c->get('name'),
                'clinicId' => $c->get('clinicId'),
                'capacity' => (int) ($c->get('capacity') ?? 1),
            ];
        }

        $filterCabinetData = $cabinetId
            ? $this->loadCabinetFilterOptions($clinicId, $service)
            : $cabinetData;
        $doctorFilterData = $this->loadDoctorFilterOptions($startUtc, $endUtc, $clinicId);
        $serviceFilterData = $this->loadServiceFilterOptions();

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
        if ($doctorId) {
            $appointmentWhere['doctorId'] = $doctorId;
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

            $parentName = $this->resolveParentName(
                $a->getParentType(),
                $a->getParentId(),
                (string) ($a->get('parentName') ?: $a->get('name'))
            );

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
                'parentName' => $parentName,
                'patientName' => $parentName,
                'status' => (string) $a->getStatus(),
            ];
        }

        return [
            'date' => $startLocal->format('Y-m-d'),
            'view' => $view,
            'timezone' => $timeZone->getName(),
            'cabinets' => $cabinetData,
            'doctors' => $doctorFilterData,
            'filters' => [
                'cabinets' => $filterCabinetData,
                'doctors' => $doctorFilterData,
                'services' => $serviceFilterData,
            ],
            'appointments' => $appData,
        ];
    }

    /**
     * @return list<array{id: string, name: string, clinicId: ?string, capacity: int}>
     */
    private function loadCabinetFilterOptions(?string $clinicId, ?Service $service = null): array
    {
        $where = ['deleted' => false];

        if ($clinicId) {
            $where['clinicId'] = $clinicId;
        }

        /** @var iterable<Cabinet> $cabinets */
        $cabinets = $this->entityManager
            ->getRDBRepository(Cabinet::ENTITY_TYPE)
            ->where($where)
            ->order('order', 'ASC')
            ->find();
        $cabinets = $this->filterCabinetsByService($cabinets, $service);

        $rows = [];

        foreach ($cabinets as $cabinet) {
            $rows[] = [
                'id' => (string) $cabinet->getId(),
                'name' => (string) $cabinet->get('name'),
                'clinicId' => $cabinet->get('clinicId'),
                'capacity' => (int) ($cabinet->get('capacity') ?? 1),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{id: string, name: string, duration: int}>
     */
    private function loadServiceFilterOptions(): array
    {
        /** @var iterable<Service> $services */
        $services = $this->entityManager
            ->getRDBRepository(Service::ENTITY_TYPE)
            ->where([
                'deleted' => false,
                'isActive' => true,
            ])
            ->order('name', 'ASC')
            ->find();

        $rows = [];

        foreach ($services as $service) {
            $rows[] = [
                'id' => (string) $service->getId(),
                'name' => (string) $service->get('name'),
                'duration' => (int) ($service->get('duration') ?: 0),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    private function loadDoctorFilterOptions(
        DateTimeImmutable $startUtc,
        DateTimeImmutable $endUtc,
        ?string $clinicId
    ): array {
        $doctors = [];
        $where = [
            'deleted' => false,
            'status' => DoctorShift::STATUS_ACTIVE,
            'type' => [DoctorShift::TYPE_REGULAR, DoctorShift::TYPE_ADDITIONAL],
            'dateStart<' => $endUtc->format('Y-m-d H:i:s'),
            'dateEnd>' => $startUtc->format('Y-m-d H:i:s'),
        ];

        if ($clinicId) {
            $where['clinicId'] = $clinicId;
        }

        /** @var iterable<DoctorShift> $shifts */
        $shifts = $this->entityManager
            ->getRDBRepository(DoctorShift::ENTITY_TYPE)
            ->where($where)
            ->find();

        foreach ($shifts as $shift) {
            $doctorId = (string) ($shift->getDoctorId() ?? '');

            if ($doctorId === '') {
                continue;
            }

            $this->addDoctorOption(
                $doctors,
                $doctorId,
                (string) ($shift->get('doctorName') ?: $this->resolveUserName($doctorId))
            );
        }

        $appointmentWhere = [
            'deleted' => false,
            'dateStart<' => $endUtc->format('Y-m-d H:i:s'),
            'dateEnd>' => $startUtc->format('Y-m-d H:i:s'),
        ];

        if ($clinicId) {
            $appointmentWhere['clinicId'] = $clinicId;
        }

        /** @var iterable<Appointment> $appointments */
        $appointments = $this->entityManager
            ->getRDBRepository(Appointment::ENTITY_TYPE)
            ->where($appointmentWhere)
            ->find();

        foreach ($appointments as $appointment) {
            $doctorId = (string) ($appointment->get('doctorId') ?? '');

            if ($doctorId === '') {
                continue;
            }

            $this->addDoctorOption(
                $doctors,
                $doctorId,
                (string) ($appointment->get('doctorName') ?: $this->resolveUserName($doctorId))
            );
        }

        $rows = array_values($doctors);
        usort(
            $rows,
            static fn (array $a, array $b): int => strcasecmp((string) $a['name'], (string) $b['name'])
        );

        return $rows;
    }

    /**
     * @param array<string, array{id: string, name: string}> $doctors
     */
    private function addDoctorOption(array &$doctors, string $doctorId, string $doctorName): void
    {
        if ($doctorId === '' || isset($doctors[$doctorId])) {
            return;
        }

        $doctors[$doctorId] = [
            'id' => $doctorId,
            'name' => trim($doctorName) !== '' ? trim($doctorName) : $doctorId,
        ];
    }

    private function resolveUserName(string $userId): string
    {
        if ($userId === '') {
            return '';
        }

        try {
            /** @var User|null $user */
            $user = $this->entityManager->getEntityById(User::ENTITY_TYPE, $userId);
        } catch (\Throwable) {
            return '';
        }

        if (!$user) {
            return '';
        }

        return (string) ($user->get('name') ?: '');
    }

    private function loadService(?string $serviceId): ?Service
    {
        if (!$serviceId) {
            return null;
        }

        /** @var Service|null $service */
        $service = $this->entityManager->getEntityById(Service::ENTITY_TYPE, $serviceId);

        if (!$service || !$service->isActive()) {
            return null;
        }

        return $service;
    }

    /**
     * @param iterable<Cabinet> $cabinets
     * @return list<Cabinet>
     */
    private function filterCabinetsByService(iterable $cabinets, ?Service $service): array
    {
        $matcher = new CabinetRequirementMatcher();
        $rows = [];

        foreach ($cabinets as $cabinet) {
            if (!$matcher->matches($service, $cabinet)) {
                continue;
            }

            $rows[] = $cabinet;
        }

        return $rows;
    }

    /**
     * Find free slots for a duration in working hours.
     *
     * @return list<array{
     *     start: string, end: string, localStart: string, localEnd: string, timezone: string,
     *     cabinetId: string, cabinetName: string,
     *     doctorId: ?string, assistantId: ?string
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
        ?string $parentId = null,
        ?string $serviceId = null
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
        $service = $this->loadService($serviceId);
        /** @var iterable<Cabinet> $cabinets */
        $cabinets = $this->entityManager
            ->getRDBRepository(Cabinet::ENTITY_TYPE)
            ->where($cabWhere)
            ->order('order', 'ASC')
            ->find();
        $cabinets = $this->filterCabinetsByService($cabinets, $service);

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
        $shiftAvailability = new DoctorShiftAvailability($this->entityManager);
        $cabinetClosures = $shiftAvailability->loadCabinetClosuresForRange(
            $from->format('Y-m-d H:i:s'),
            $to->format('Y-m-d H:i:s'),
            $clinicId
        );
        $doctorSchedule = $doctorId
            ? $shiftAvailability->loadForRange(
                $doctorId,
                $from->format('Y-m-d H:i:s'),
                $to->format('Y-m-d H:i:s'),
                $clinicId
            )
            : null;

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
                    if ($shiftAvailability->isCabinetClosed($cabinetClosures, $t, $t + $durSec, $cId)) {
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
                    $assistantId = null;
                    if ($doctorSchedule !== null) {
                        $shiftResult = $shiftAvailability->evaluateSlot(
                            $doctorSchedule,
                            $t,
                            $t + $durSec,
                            $cId
                        );

                        if (!$shiftResult['available']) {
                            continue;
                        }

                        $assistantId = $shiftResult['assistantId'];
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
                        'assistantId' => $assistantId,
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

    private function resolveParentName(?string $parentType, ?string $parentId, string $fallback = ''): string
    {
        if (!$parentType || !$parentId) {
            return $fallback;
        }

        try {
            $parent = $this->entityManager->getEntityById($parentType, $parentId);
        } catch (\Throwable) {
            return $fallback;
        }

        if (!$parent) {
            return $fallback;
        }

        return (string) ($parent->get('name') ?: $fallback);
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
