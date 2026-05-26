<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\Appointment;
use Espo\Modules\EspoDental\Entities\AppointmentRescheduleRequest;
use Espo\Modules\EspoDental\Entities\AppointmentWaitlistEntry;
use Espo\Modules\EspoDental\Entities\Patient;

class CalendarFeedbackService
{
    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getFeedbackPanel(
        string $date,
        ?string $clinicId = null,
        int $limit = 12,
        ?string $doctorId = null,
        ?string $cabinetId = null
    ): array {
        $day = $this->normalizeDate($date);
        $limit = max(1, min(30, $limit));

        return [
            'date' => $day->format('Y-m-d'),
            'clinicId' => $clinicId,
            'doctorId' => $doctorId,
            'cabinetId' => $cabinetId,
            'waitlist' => $this->getWaitlist($day, $clinicId, $limit, $doctorId, $cabinetId),
            'cancelled' => $this->getCancelledAppointments($day, $clinicId, $limit, $doctorId, $cabinetId),
            'rescheduleRequests' => $this->getRescheduleRequests($day, $clinicId, $limit, $doctorId, $cabinetId),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getWaitlist(
        DateTimeImmutable $day,
        ?string $clinicId,
        int $limit,
        ?string $doctorId,
        ?string $cabinetId
    ): array {
        $where = [
            'deleted' => false,
            'status' => [
                AppointmentWaitlistEntry::STATUS_WAITING,
                AppointmentWaitlistEntry::STATUS_OFFERED,
            ],
        ];

        if ($clinicId) {
            $where['clinicId'] = $clinicId;
        }
        if ($doctorId) {
            $where['requestedDoctorId'] = $doctorId;
        }
        if ($cabinetId) {
            $where['preferredCabinetId'] = $cabinetId;
        }

        /** @var iterable<AppointmentWaitlistEntry> $entries */
        $entries = $this->entityManager
            ->getRDBRepository(AppointmentWaitlistEntry::ENTITY_TYPE)
            ->where($where)
            ->order('priority', 'DESC')
            ->order('createdAt', 'ASC')
            ->find();

        $rows = [];
        foreach ($entries as $entry) {
            $requestedDate = (string) ($entry->get('requestedDate') ?? '');

            if ($requestedDate !== '' && $requestedDate > $day->format('Y-m-d')) {
                continue;
            }

            $parentName = $this->resolveParentName(
                (string) ($entry->get('parentType') ?? ''),
                (string) ($entry->get('parentId') ?? ''),
                (string) ($entry->get('parentName') ?: $entry->get('name'))
            );

            $rows[] = [
                'id' => (string) $entry->getId(),
                'name' => (string) ($entry->get('name') ?? ''),
                'status' => (string) ($entry->get('status') ?? ''),
                'priority' => (string) ($entry->get('priority') ?? ''),
                'parentType' => (string) ($entry->get('parentType') ?? ''),
                'parentId' => (string) ($entry->get('parentId') ?? ''),
                'parentName' => $parentName,
                'patientName' => $parentName,
                'requestedDoctorId' => (string) ($entry->get('requestedDoctorId') ?? ''),
                'requestedDoctorName' => (string) ($entry->get('requestedDoctorName') ?? ''),
                'preferredCabinetId' => (string) ($entry->get('preferredCabinetId') ?? ''),
                'preferredCabinetName' => (string) ($entry->get('preferredCabinetName') ?? ''),
                'requestedDate' => $requestedDate,
                'earliestDate' => (string) ($entry->get('earliestDate') ?? ''),
                'latestDate' => (string) ($entry->get('latestDate') ?? ''),
                'reason' => (string) ($entry->get('reason') ?? ''),
            ];

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getRescheduleRequests(
        DateTimeImmutable $day,
        ?string $clinicId,
        int $limit,
        ?string $doctorId,
        ?string $cabinetId
    ): array {
        $from = $day->setTime(0, 0);
        $to = $from->modify('+1 day');
        $where = [
            'deleted' => false,
            'status' => AppointmentRescheduleRequest::ACTIVE_STATUSES,
            'requestedStartAt>=' => $from->format('Y-m-d H:i:s'),
            'requestedStartAt<' => $to->format('Y-m-d H:i:s'),
        ];

        if ($clinicId) {
            $where['requestedClinicId'] = $clinicId;
        }
        if ($doctorId) {
            $where['requestedDoctorId'] = $doctorId;
        }
        if ($cabinetId) {
            $where['requestedCabinetId'] = $cabinetId;
        }

        /** @var iterable<AppointmentRescheduleRequest> $requests */
        $requests = $this->entityManager
            ->getRDBRepository(AppointmentRescheduleRequest::ENTITY_TYPE)
            ->where($where)
            ->order('createdAt', 'ASC')
            ->find();

        $rows = [];
        foreach ($requests as $request) {
            $patientName = $this->resolveParentName(
                Patient::ENTITY_TYPE,
                (string) ($request->get('patientId') ?? ''),
                (string) ($request->get('patientName') ?: $request->get('name'))
            );

            $rows[] = [
                'id' => (string) $request->getId(),
                'name' => (string) ($request->get('name') ?? ''),
                'status' => (string) ($request->get('status') ?? ''),
                'appointmentId' => (string) ($request->get('appointmentId') ?? ''),
                'patientId' => (string) ($request->get('patientId') ?? ''),
                'patientName' => $patientName,
                'requestedStartAt' => (string) ($request->get('requestedStartAt') ?? ''),
                'requestedEndAt' => (string) ($request->get('requestedEndAt') ?? ''),
                'requestedDoctorId' => (string) ($request->get('requestedDoctorId') ?? ''),
                'requestedDoctorName' => (string) ($request->get('requestedDoctorName') ?? ''),
                'requestedCabinetId' => (string) ($request->get('requestedCabinetId') ?? ''),
                'requestedCabinetName' => (string) ($request->get('requestedCabinetName') ?? ''),
                'source' => (string) ($request->get('source') ?? ''),
                'patientComment' => (string) ($request->get('patientComment') ?? ''),
            ];

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getCancelledAppointments(
        DateTimeImmutable $day,
        ?string $clinicId,
        int $limit,
        ?string $doctorId,
        ?string $cabinetId
    ): array {
        $from = $day->setTime(0, 0);
        $to = $from->modify('+1 day');

        $where = [
            'deleted' => false,
            'status' => [Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW],
            'dateStart>=' => $from->format('Y-m-d H:i:s'),
            'dateStart<' => $to->format('Y-m-d H:i:s'),
        ];

        if ($clinicId) {
            $where['clinicId'] = $clinicId;
        }
        if ($doctorId) {
            $where['doctorId'] = $doctorId;
        }
        if ($cabinetId) {
            $where['cabinetId'] = $cabinetId;
        }

        /** @var iterable<Appointment> $appointments */
        $appointments = $this->entityManager
            ->getRDBRepository(Appointment::ENTITY_TYPE)
            ->where($where)
            ->order('dateStart', 'ASC')
            ->find();

        $rows = [];
        foreach ($appointments as $appointment) {
            $parentName = $this->resolveParentName(
                $appointment->getParentType(),
                $appointment->getParentId(),
                (string) ($appointment->get('parentName') ?: $appointment->get('name'))
            );

            $rows[] = [
                'id' => (string) $appointment->getId(),
                'name' => (string) ($appointment->get('name') ?? ''),
                'status' => (string) $appointment->getStatus(),
                'dateStart' => (string) $appointment->getDateStart(),
                'dateEnd' => (string) $appointment->getDateEnd(),
                'parentType' => (string) ($appointment->get('parentType') ?? ''),
                'parentId' => (string) ($appointment->get('parentId') ?? ''),
                'parentName' => $parentName,
                'patientName' => $parentName,
                'doctorName' => (string) ($appointment->get('doctorName') ?? ''),
                'cabinetName' => (string) ($appointment->get('cabinetName') ?? ''),
            ];

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
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

    private function normalizeDate(string $date): DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return new DateTimeImmutable('today', new DateTimeZone('UTC'));
        }

        return new DateTimeImmutable($date . ' 00:00:00', new DateTimeZone('UTC'));
    }
}
