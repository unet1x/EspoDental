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
            $this->entityManager->saveEntity($appointment);

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
        $visit->set('name', $this->buildVisitName($appointment));

        $this->entityManager->saveEntity($visit);

        $appointment->set('status', Appointment::STATUS_IN_PROGRESS);
        $this->entityManager->saveEntity($appointment);

        return [
            'visitId' => (string) $visit->getId(),
            'appointmentId' => (string) $appointment->getId(),
        ];
    }

    private function buildVisitName(Appointment $appointment): string
    {
        $date = substr((string) $appointment->getDateStart(), 0, 10);
        $parent = $appointment->get('parentName') ?: 'patient';
        return $parent . ' — ' . $date;
    }
}
