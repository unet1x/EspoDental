<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\Appointment;
use Espo\Modules\EspoDental\Entities\Patient;
use Espo\Modules\EspoDental\Entities\PreliminaryPatient;
use Espo\Modules\EspoDental\Entities\ToothChartSnapshot;
use Espo\Modules\EspoDental\Entities\Visit;
use Espo\ORM\Entity;

class AppointmentService
{
    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchBookingCandidates(string $query, int $limit = 10): array
    {
        $query = trim($query);
        $limit = max(1, min(20, $limit));

        if (mb_strlen($query) < 2) {
            return [];
        }

        $rows = [];
        $this->appendCandidateRows($rows, Patient::ENTITY_TYPE, $query, $limit);

        if (count($rows) < $limit) {
            $this->appendCandidateRows($rows, PreliminaryPatient::ENTITY_TYPE, $query, $limit);
        }

        return array_slice($rows, 0, $limit);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{appointmentId: string, parentType: string, parentId: string, createdPreliminaryPatient: bool}
     */
    public function bookFromSlot(array $data): array
    {
        $durationMinutes = (int) ($data['durationMinutes'] ?? 30);

        if ($durationMinutes < 15 || $durationMinutes > 180) {
            throw new BadRequest('durationMinutes must be between 15 and 180');
        }

        $clinicId = trim((string) ($data['clinicId'] ?? ''));
        $cabinetId = trim((string) ($data['cabinetId'] ?? ''));
        $doctorId = trim((string) ($data['doctorId'] ?? ''));

        if ($clinicId === '' || $cabinetId === '' || $doctorId === '') {
            throw new BadRequest('clinicId, cabinetId and doctorId are required');
        }

        $start = $this->parseSlotStart(
            (string) ($data['localStart'] ?? $data['dateStart'] ?? ''),
            (string) ($data['timezone'] ?? 'UTC')
        );

        [$parentType, $parentId, $createdPreliminaryPatient] = $this->resolveBookingParent($data, $clinicId);

        /** @var Appointment $appointment */
        $appointment = $this->entityManager->getNewEntity(Appointment::ENTITY_TYPE);
        $appointment->set('parentType', $parentType);
        $appointment->set('parentId', $parentId);
        $appointment->set('clinicId', $clinicId);
        $appointment->set('cabinetId', $cabinetId);
        $appointment->set('doctorId', $doctorId);
        $appointment->set('dateStart', $start->format('Y-m-d H:i:s'));
        $appointment->set('duration', $durationMinutes * 60);
        $appointment->set('status', Appointment::STATUS_PLANNED);
        $appointment->set('complaints', trim((string) ($data['reason'] ?? '')));
        $appointment->set('description', trim((string) ($data['notes'] ?? '')));

        $this->entityManager->saveEntity($appointment);

        return [
            'appointmentId' => (string) $appointment->getId(),
            'parentType' => $parentType,
            'parentId' => $parentId,
            'createdPreliminaryPatient' => $createdPreliminaryPatient,
        ];
    }

    /**
     * @return array{visitId: string, appointmentId: string}
     */
    public function startVisit(string $appointmentId): array
    {
        /** @var Appointment|null $appointment */
        $appointment = $this->entityManager->getEntityById(Appointment::ENTITY_TYPE, $appointmentId);

        if (!$appointment) {
            throw new NotFound('Appointment not found');
        }

        if ($appointment->getParentType() !== Patient::ENTITY_TYPE) {
            throw new BadRequest('Convert the lead patient first');
        }

        /** @var Patient|null $patient */
        $patient = $this->entityManager->getEntityById(Patient::ENTITY_TYPE, (string) $appointment->getParentId());

        if (!$patient) {
            throw new NotFound('Patient not found');
        }

        if (!$patient->get('lastQuestionnaireAt') || (bool) $patient->get('questionnaireExpired')) {
            throw new Conflict('Health questionnaire must be completed before the visit can start');
        }

        if (
            !in_array($appointment->getStatus(), [
            Appointment::STATUS_PLANNED,
            Appointment::STATUS_RESCHEDULED,
            Appointment::STATUS_ARRIVED,
            ], true)
        ) {
            throw new Conflict('Visit cannot be started from status: ' . (string) $appointment->getStatus());
        }

        $existingVisit = $this->entityManager
            ->getRDBRepository(Visit::ENTITY_TYPE)
            ->where(['appointmentId' => $appointment->getId()])
            ->findOne();

        if ($existingVisit) {
            $appointment->set('status', Appointment::STATUS_IN_PROGRESS);
            $this->entityManager->saveEntity($appointment, ['espodentalAllowAppointmentSystemStatus' => true]);
            $this->ensureToothChartSnapshot($existingVisit, $patient);

            return [
                'visitId' => (string) $existingVisit->getId(),
                'appointmentId' => (string) $appointment->getId(),
            ];
        }

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        /** @var Visit $visit */
        $visit = $this->entityManager->getNewEntity(Visit::ENTITY_TYPE);
        $visit->set('appointmentId', $appointment->getId());
        $visit->set('patientId', $appointment->getParentId());
        $visit->set('doctorId', $appointment->getDoctorId());
        $visit->set('assistantId', $appointment->get('assistantId'));
        $visit->set('cabinetId', $appointment->getCabinetId());
        $visit->set('clinicId', $appointment->getClinicId());
        $visit->set('status', Visit::STATUS_IN_PROGRESS);
        $visit->set('complaints', $appointment->get('complaints'));
        $visit->set('startedAt', $now);
        $visit->set('name', $this->buildVisitName($appointment, $patient));

        $this->entityManager->saveEntity($visit, ['espodentalAllowVisitCreate' => true]);
        $this->ensureToothChartSnapshot($visit, $patient);

        $appointment->set('status', Appointment::STATUS_IN_PROGRESS);
        $this->entityManager->saveEntity($appointment, ['espodentalAllowAppointmentSystemStatus' => true]);

        return [
            'visitId' => (string) $visit->getId(),
            'appointmentId' => (string) $appointment->getId(),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function appendCandidateRows(array &$rows, string $entityType, string $query, int $limit): void
    {
        $where = [
            'deleted' => false,
            'OR' => [
                ['lastName*' => $query . '%'],
                ['firstName*' => $query . '%'],
                ['phone*' => '%' . $query . '%'],
                ['emailAddress*' => '%' . $query . '%'],
            ],
        ];

        if ($entityType === PreliminaryPatient::ENTITY_TYPE) {
            $where['status!='] = PreliminaryPatient::STATUS_PROCESSED;
        }

        /** @var iterable<Entity> $entities */
        $entities = $this->entityManager
            ->getRDBRepository($entityType)
            ->where($where)
            ->order('lastName', 'ASC')
            ->find();

        foreach ($entities as $entity) {
            $rows[] = [
                'entityType' => $entityType,
                'id' => (string) $entity->getId(),
                'name' => $this->buildPersonName($entity),
                'phone' => (string) ($entity->get('phone') ?? ''),
                'emailAddress' => (string) ($entity->get('emailAddress') ?? ''),
                'clinicId' => (string) ($entity->get('clinicId') ?? ''),
            ];

            if (count($rows) >= $limit) {
                break;
            }
        }
    }

    private function parseSlotStart(string $value, string $timeZone): DateTimeImmutable
    {
        if (trim($value) === '') {
            throw new BadRequest('dateStart is required');
        }

        try {
            return (new DateTimeImmutable($value, $this->buildTimeZone($timeZone)))
                ->setTimezone(new DateTimeZone('UTC'));
        } catch (\Exception $e) {
            throw new BadRequest('Invalid slot datetime: ' . $e->getMessage());
        }
    }

    private function buildTimeZone(string $timeZone): DateTimeZone
    {
        try {
            return new DateTimeZone($timeZone !== '' ? $timeZone : 'UTC');
        } catch (\Exception) {
            return new DateTimeZone('UTC');
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array{string, string, bool}
     */
    private function resolveBookingParent(array $data, string $clinicId): array
    {
        $parentType = trim((string) ($data['parentType'] ?? ''));
        $parentId = trim((string) ($data['parentId'] ?? ''));

        if ($parentType !== '' || $parentId !== '') {
            $allowedParentTypes = [Patient::ENTITY_TYPE, PreliminaryPatient::ENTITY_TYPE];

            if (!in_array($parentType, $allowedParentTypes, true) || $parentId === '') {
                throw new BadRequest('Valid parentType and parentId are required');
            }

            $parent = $this->entityManager->getEntityById($parentType, $parentId);
            if (!$parent) {
                throw new NotFound('Booking parent not found');
            }

            return [$parentType, $parentId, false];
        }

        /** @var PreliminaryPatient $preliminary */
        $preliminary = $this->entityManager->getNewEntity(PreliminaryPatient::ENTITY_TYPE);
        $preliminary->set('lastName', trim((string) ($data['lastName'] ?? '')));
        $preliminary->set('firstName', trim((string) ($data['firstName'] ?? '')));
        $preliminary->set('middleName', trim((string) ($data['middleName'] ?? '')));
        $preliminary->set('phone', trim((string) ($data['phone'] ?? '')));
        $preliminary->set('emailAddress', trim((string) ($data['emailAddress'] ?? '')));
        $preliminary->set('clinicId', $clinicId);
        $preliminary->set('status', PreliminaryPatient::STATUS_ENTERED);
        $preliminary->set('source', 'phone');
        $preliminary->set('description', trim((string) ($data['notes'] ?? '')));

        if ((string) $preliminary->get('lastName') === '' || (string) $preliminary->get('firstName') === '') {
            throw new BadRequest('lastName and firstName are required for a new preliminary patient');
        }

        if ((string) $preliminary->get('phone') === '') {
            throw new BadRequest('phone is required for a new preliminary patient');
        }

        $this->entityManager->saveEntity($preliminary);

        return [PreliminaryPatient::ENTITY_TYPE, (string) $preliminary->getId(), true];
    }

    private function buildVisitName(Appointment $appointment, Patient $patient): string
    {
        $date = substr((string) $appointment->getDateStart(), 0, 10);
        $parent = $this->buildPatientName($patient);
        return $parent . ' — ' . $date;
    }

    private function buildPatientName(Patient $patient): string
    {
        return $this->buildPersonName($patient) ?: 'Пациент';
    }

    private function buildPersonName(Entity $entity): string
    {
        $parts = array_filter([
            trim((string) $entity->get('lastName')),
            trim((string) $entity->get('firstName')),
            trim((string) $entity->get('middleName')),
        ]);

        $fullName = trim(implode(' ', $parts));

        return $fullName !== '' ? $fullName : (string) ($entity->get('name') ?? '');
    }

    private function ensureToothChartSnapshot(Visit $visit, Patient $patient): void
    {
        $existing = $this->entityManager
            ->getRDBRepository(ToothChartSnapshot::ENTITY_TYPE)
            ->where(['visitId' => $visit->getId()])
            ->findOne();

        if ($existing) {
            return;
        }

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        /** @var ToothChartSnapshot $snapshot */
        $snapshot = $this->entityManager->getNewEntity(ToothChartSnapshot::ENTITY_TYPE);
        $snapshot->set('name', 'Зубная формула — ' . (string) $visit->get('name'));
        $snapshot->set('patientId', $patient->getId());
        $snapshot->set('visitId', $visit->getId());
        $snapshot->set('doctorId', $visit->getDoctorId());
        $snapshot->set(
            'dentitionType',
            $patient->isChild() ? ToothChartSnapshot::DENTITION_MIXED : ToothChartSnapshot::DENTITION_ADULT
        );
        $snapshot->set('teeth', (object) []);
        $snapshot->set('recordedAt', $now);

        $this->entityManager->saveEntity($snapshot);
    }
}
