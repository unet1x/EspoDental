<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config;
use Espo\Modules\EspoDental\Entities\Appointment;
use Espo\Modules\EspoDental\Entities\Clinic;
use Espo\Modules\EspoDental\Entities\Patient;
use Espo\Modules\EspoDental\Entities\Visit;

class PatientHistoryService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Config $config
    ) {
    }

    /**
     * @return array{
     *     patientId: string,
     *     futureAppointments: list<array<string, mixed>>,
     *     pastVisits: list<array<string, mixed>>
     * }
     */
    public function getPatientHistory(
        string $patientId,
        bool $includeAppointments = true,
        bool $includeVisits = true,
        int $limit = 8
    ): array {
        /** @var Patient|null $patient */
        $patient = $this->entityManager->getEntityById(Patient::ENTITY_TYPE, $patientId);

        if (!$patient) {
            throw new NotFound("Patient {$patientId} not found");
        }

        $limit = max(1, min(30, $limit));

        return [
            'patientId' => (string) $patient->getId(),
            'futureAppointments' => $includeAppointments ? $this->getFutureAppointments($patientId, $limit) : [],
            'pastVisits' => $includeVisits ? $this->getPastVisits($patientId, $limit) : [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getFutureAppointments(string $patientId, int $limit): array
    {
        $nowUtc = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        /** @var iterable<Appointment> $appointments */
        $appointments = $this->entityManager
            ->getRDBRepository(Appointment::ENTITY_TYPE)
            ->where([
                'deleted' => false,
                'parentType' => Patient::ENTITY_TYPE,
                'parentId' => $patientId,
                'status' => Appointment::BLOCKING_STATUSES,
                'dateStart>=' => $nowUtc,
            ])
            ->order('dateStart', 'ASC')
            ->find();

        $rows = [];
        foreach ($appointments as $appointment) {
            $timeZone = $this->resolveTimeZone($appointment->getClinicId());

            $rows[] = [
                'id' => (string) $appointment->getId(),
                'name' => (string) $appointment->get('name'),
                'status' => (string) $appointment->getStatus(),
                'dateStart' => (string) $appointment->getDateStart(),
                'dateEnd' => (string) $appointment->getDateEnd(),
                'localStart' => $this->formatLocalDateTime($appointment->getDateStart(), $timeZone),
                'localEnd' => $this->formatLocalDateTime($appointment->getDateEnd(), $timeZone),
                'timezone' => $timeZone->getName(),
                'doctorId' => $appointment->get('doctorId'),
                'doctorName' => $appointment->get('doctorName'),
                'cabinetId' => $appointment->get('cabinetId'),
                'cabinetName' => $appointment->get('cabinetName'),
                'clinicId' => $appointment->get('clinicId'),
                'clinicName' => $appointment->get('clinicName'),
                'complaints' => $appointment->get('complaints'),
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
    private function getPastVisits(string $patientId, int $limit): array
    {
        $nowUtc = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        /** @var iterable<Visit> $visits */
        $visits = $this->entityManager
            ->getRDBRepository(Visit::ENTITY_TYPE)
            ->where([
                'deleted' => false,
                'patientId' => $patientId,
                'startedAt<=' => $nowUtc,
            ])
            ->order('startedAt', 'DESC')
            ->find();

        $rows = [];
        foreach ($visits as $visit) {
            $timeZone = $this->resolveTimeZone((string) ($visit->get('clinicId') ?: ''));

            $rows[] = [
                'id' => (string) $visit->getId(),
                'name' => (string) $visit->get('name'),
                'status' => (string) $visit->getStatus(),
                'startedAt' => (string) $visit->get('startedAt'),
                'finishedAt' => (string) $visit->get('finishedAt'),
                'localStartedAt' => $this->formatLocalDateTime((string) $visit->get('startedAt'), $timeZone),
                'localFinishedAt' => $this->formatLocalDateTime((string) $visit->get('finishedAt'), $timeZone),
                'timezone' => $timeZone->getName(),
                'doctorId' => $visit->get('doctorId'),
                'doctorName' => $visit->get('doctorName'),
                'cabinetId' => $visit->get('cabinetId'),
                'cabinetName' => $visit->get('cabinetName'),
                'clinicId' => $visit->get('clinicId'),
                'clinicName' => $visit->get('clinicName'),
                'totalAmount' => $visit->get('totalAmount'),
            ];

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
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

    private function formatLocalDateTime(?string $value, DateTimeZone $timeZone): string
    {
        if (!$value) {
            return '';
        }

        try {
            return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
                ->setTimezone($timeZone)
                ->format('Y-m-d H:i:s');
        } catch (\Exception) {
            return '';
        }
    }
}
