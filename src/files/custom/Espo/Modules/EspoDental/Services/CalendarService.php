<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\Appointment;
use Espo\Modules\EspoDental\Entities\Cabinet;

class CalendarService
{
    public function __construct(private readonly EntityManager $entityManager)
    {
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
    public function getDayData(string $date, ?string $clinicId, string $view = 'day'): array
    {
        $start = new DateTimeImmutable($date . ' 00:00:00');
        $end = match ($view) {
            'week' => $start->modify('+7 days'),
            default => $start->modify('+1 day'),
        };

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
