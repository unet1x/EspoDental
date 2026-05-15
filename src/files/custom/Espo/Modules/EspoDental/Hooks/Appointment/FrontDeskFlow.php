<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\Appointment;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config;
use Espo\Entities\User;
use Espo\Modules\EspoDental\Entities\Appointment;
use Espo\Modules\EspoDental\Entities\Clinic;
use Espo\Modules\EspoDental\Entities\PreliminaryPatient;
use Espo\ORM\Entity;

class FrontDeskFlow
{
    public static int $order = 1;

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly User $user,
        private readonly Config $config
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function beforeSave(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof Appointment) {
            return;
        }

        $this->guardSystemStatuses($entity, $options);
        $this->applyDefaultStatus($entity);
        $this->applyDefaultClinic($entity);
        $this->applyDateEndFromDuration($entity);
        $entity->set('name', $this->buildAppointmentName($entity));

        if ($entity->isNew() && !$entity->get('bookedById')) {
            $entity->set('bookedById', $this->user->getId());
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    public function afterSave(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof Appointment) {
            return;
        }

        if ($entity->getParentType() !== PreliminaryPatient::ENTITY_TYPE || !$entity->getParentId()) {
            return;
        }

        /** @var PreliminaryPatient|null $prelim */
        $prelim = $this->entityManager->getEntityById(
            PreliminaryPatient::ENTITY_TYPE,
            (string) $entity->getParentId()
        );

        if (!$prelim || $prelim->isConverted()) {
            return;
        }

        $newStatus = match ($entity->getStatus()) {
            Appointment::STATUS_NO_SHOW => PreliminaryPatient::STATUS_NO_SHOW,
            Appointment::STATUS_CANCELLED => PreliminaryPatient::STATUS_ENTERED,
            default => PreliminaryPatient::STATUS_BOOKED,
        };

        if ($prelim->getStatus() === $newStatus) {
            return;
        }

        $prelim->set('status', $newStatus);
        $this->entityManager->saveEntity($prelim, ['skipHooks' => true]);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function guardSystemStatuses(Appointment $appointment, array $options): void
    {
        if (!empty($options['espodentalAllowAppointmentSystemStatus'])) {
            return;
        }

        $status = (string) $appointment->get('status');

        if (!in_array($status, [Appointment::STATUS_IN_PROGRESS, Appointment::STATUS_FINISHED], true)) {
            return;
        }

        $oldStatus = (string) ($appointment->getFetched('status') ?? '');
        if (!$appointment->isNew() && $oldStatus === $status) {
            return;
        }

        throw new BadRequest('Appointment status is controlled by visit workflow');
    }

    private function applyDefaultStatus(Appointment $appointment): void
    {
        if (!$appointment->get('status')) {
            $appointment->set('status', Appointment::STATUS_PLANNED);
        }
    }

    private function applyDefaultClinic(Appointment $appointment): void
    {
        if ($appointment->getClinicId()) {
            return;
        }

        $clinicId = $this->resolveDefaultClinicId();

        if ($clinicId) {
            $appointment->set('clinicId', $clinicId);
        }
    }

    private function applyDateEndFromDuration(Appointment $appointment): void
    {
        if (!$appointment->getDateStart()) {
            return;
        }

        $durationChanged = $appointment->get('duration') !== $appointment->getFetched('duration');
        $dateChanged = $appointment->isNew()
            || $appointment->getDateStart() !== $appointment->getFetched('dateStart')
            || $durationChanged;
        $dateEndChanged = !$appointment->isNew()
            && $appointment->getDateEnd() !== $appointment->getFetched('dateEnd');

        if ($dateEndChanged && !$durationChanged) {
            return;
        }

        if (!$dateChanged && $appointment->getDateEnd()) {
            return;
        }

        $duration = (int) ($appointment->get('duration') ?: 1800);
        $duration = $duration > 0 ? $duration : 1800;

        try {
            $start = $this->createUtcDateTime((string) $appointment->getDateStart());
            $appointment->set('dateEnd', $start->add(new DateInterval('PT' . $duration . 'S'))->format('Y-m-d H:i:s'));
        } catch (\Exception) {
            return;
        }
    }

    private function buildAppointmentName(Appointment $appointment): string
    {
        $date = $this->formatClinicDateTime($appointment);

        return $date . ' — ' . $this->translateStatus((string) $appointment->getStatus());
    }

    private function formatClinicDateTime(Appointment $appointment): string
    {
        $dateStart = (string) $appointment->getDateStart();

        if ($dateStart === '') {
            return 'Без даты';
        }

        try {
            return $this
                ->createUtcDateTime($dateStart)
                ->setTimezone($this->resolveTimeZone($appointment->getClinicId()))
                ->format('Y-m-d H:i');
        } catch (\Exception) {
            return substr($dateStart, 0, 16);
        }
    }

    private function createUtcDateTime(string $value): DateTimeImmutable
    {
        $utc = new DateTimeZone('UTC');
        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $utc);

        if ($date instanceof DateTimeImmutable) {
            return $date;
        }

        return new DateTimeImmutable($value, $utc);
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

    private function translateStatus(string $status): string
    {
        return match ($status) {
            Appointment::STATUS_PLANNED => 'Запланировано',
            Appointment::STATUS_RESCHEDULED => 'Перенесено',
            Appointment::STATUS_CANCELLED => 'Отменено',
            Appointment::STATUS_ARRIVED => 'Пациент явился',
            Appointment::STATUS_IN_PROGRESS => 'Идёт приём',
            Appointment::STATUS_FINISHED => 'Завершено',
            Appointment::STATUS_NO_SHOW => 'Не явился',
            default => $status !== '' ? $status : 'Запланировано',
        };
    }

    private function resolveDefaultClinicId(): ?string
    {
        $configuredId = (string) $this->config->get('espoDentalDefaultClinicId', '');

        if ($configuredId !== '') {
            /** @var Clinic|null $clinic */
            $clinic = $this->entityManager->getEntityById(Clinic::ENTITY_TYPE, $configuredId);

            if ($clinic && $clinic->isActive()) {
                return $configuredId;
            }
        }

        $activeClinics = $this->entityManager
            ->getRDBRepository(Clinic::ENTITY_TYPE)
            ->where(['isActive' => true])
            ->find();

        $singleId = null;
        $count = 0;

        foreach ($activeClinics as $clinic) {
            $count++;

            if ($count > 1) {
                return null;
            }

            $singleId = (string) $clinic->getId();
        }

        return $count === 1 ? $singleId : null;
    }
}
