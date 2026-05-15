<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\Appointment;
use Espo\Modules\EspoDental\Entities\Patient;
use Espo\Modules\EspoDental\Entities\ToothChartSnapshot;
use Espo\Modules\EspoDental\Entities\Visit;

class AppointmentService
{
    public function __construct(private readonly EntityManager $entityManager)
    {
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

    private function buildVisitName(Appointment $appointment, Patient $patient): string
    {
        $date = substr((string) $appointment->getDateStart(), 0, 10);
        $parent = $this->buildPatientName($patient);
        return $parent . ' — ' . $date;
    }

    private function buildPatientName(Patient $patient): string
    {
        $parts = array_filter([
            trim((string) $patient->get('lastName')),
            trim((string) $patient->get('firstName')),
            trim((string) $patient->get('middleName')),
        ]);

        $fullName = trim(implode(' ', $parts));

        return $fullName !== '' ? $fullName : ((string) $patient->get('name') ?: 'Пациент');
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
