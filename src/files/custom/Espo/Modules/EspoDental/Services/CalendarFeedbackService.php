<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\Appointment;
use Espo\Modules\EspoDental\Entities\AppointmentWaitlistEntry;

class CalendarFeedbackService
{
    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getFeedbackPanel(string $date, ?string $clinicId = null, int $limit = 12): array
    {
        $day = $this->normalizeDate($date);
        $limit = max(1, min(30, $limit));

        return [
            'date' => $day->format('Y-m-d'),
            'clinicId' => $clinicId,
            'waitlist' => $this->getWaitlist($day, $clinicId, $limit),
            'cancelled' => $this->getCancelledAppointments($day, $clinicId, $limit),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getWaitlist(DateTimeImmutable $day, ?string $clinicId, int $limit): array
    {
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

            $rows[] = [
                'id' => (string) $entry->getId(),
                'name' => (string) ($entry->get('name') ?? ''),
                'status' => (string) ($entry->get('status') ?? ''),
                'priority' => (string) ($entry->get('priority') ?? ''),
                'parentType' => (string) ($entry->get('parentType') ?? ''),
                'parentId' => (string) ($entry->get('parentId') ?? ''),
                'parentName' => (string) ($entry->get('parentName') ?: $entry->get('name')),
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
    private function getCancelledAppointments(DateTimeImmutable $day, ?string $clinicId, int $limit): array
    {
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

        /** @var iterable<Appointment> $appointments */
        $appointments = $this->entityManager
            ->getRDBRepository(Appointment::ENTITY_TYPE)
            ->where($where)
            ->order('dateStart', 'ASC')
            ->find();

        $rows = [];
        foreach ($appointments as $appointment) {
            $rows[] = [
                'id' => (string) $appointment->getId(),
                'name' => (string) ($appointment->get('name') ?? ''),
                'status' => (string) $appointment->getStatus(),
                'dateStart' => (string) $appointment->getDateStart(),
                'dateEnd' => (string) $appointment->getDateEnd(),
                'parentType' => (string) ($appointment->get('parentType') ?? ''),
                'parentId' => (string) ($appointment->get('parentId') ?? ''),
                'parentName' => (string) ($appointment->get('parentName') ?: $appointment->get('name')),
                'doctorName' => (string) ($appointment->get('doctorName') ?? ''),
                'cabinetName' => (string) ($appointment->get('cabinetName') ?? ''),
            ];

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    private function normalizeDate(string $date): DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return new DateTimeImmutable('today', new DateTimeZone('UTC'));
        }

        return new DateTimeImmutable($date . ' 00:00:00', new DateTimeZone('UTC'));
    }
}
