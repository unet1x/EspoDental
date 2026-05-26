<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\ORM\EntityManager;
use Espo\Entities\User;
use Espo\Modules\EspoDental\Entities\Appointment;
use Espo\Modules\EspoDental\Entities\AssistantActionProposal;
use Espo\Modules\EspoDental\Entities\LowStockAlert;
use Espo\ORM\Entity;

class DashboardActionCenterService
{
    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getActionCenter(User $user, ?string $clinicId = null, int $limit = 8): array
    {
        $limit = max(1, min(20, $limit));
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $todayStart = $now->setTime(0, 0);
        $tomorrowStart = $todayStart->modify('+1 day');

        $waitingPatients = $this->getWaitingPatients($todayStart, $tomorrowStart, $clinicId, $limit);
        $pendingActions = $this->getPendingActions($clinicId, $limit);
        $assignedTasks = $this->getAssignedTasks((string) $user->getId(), $limit);
        $alerts = $this->getLowStockAlerts($clinicId, $limit);
        $weeklyWorkload = $this->getWeeklyWorkload($todayStart, $clinicId);

        return [
            'generatedAt' => $now->format('Y-m-d H:i:s'),
            'clinicId' => $clinicId,
            'limit' => $limit,
            'summary' => [
                'waitingPatients' => count($waitingPatients),
                'pendingActions' => count($pendingActions),
                'assignedTasks' => count($assignedTasks),
                'openAlerts' => count($alerts),
                'weekAppointments' => array_sum(array_column($weeklyWorkload, 'appointmentCount')),
            ],
            'waitingPatients' => $waitingPatients,
            'pendingActions' => $pendingActions,
            'assignedTasks' => $assignedTasks,
            'alerts' => $alerts,
            'weeklyWorkload' => $weeklyWorkload,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getWaitingPatients(
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        ?string $clinicId,
        int $limit
    ): array {
        $where = [
            'deleted' => false,
            'status' => [Appointment::STATUS_ARRIVED, Appointment::STATUS_IN_PROGRESS],
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
                'name' => (string) $appointment->get('name'),
                'status' => (string) $appointment->getStatus(),
                'dateStart' => (string) $appointment->getDateStart(),
                'dateEnd' => (string) $appointment->getDateEnd(),
                'parentType' => (string) ($appointment->get('parentType') ?? ''),
                'parentId' => (string) ($appointment->get('parentId') ?? ''),
                'parentName' => (string) ($appointment->get('parentName') ?: $appointment->get('name')),
                'doctorId' => (string) ($appointment->get('doctorId') ?? ''),
                'doctorName' => (string) ($appointment->get('doctorName') ?? ''),
                'cabinetId' => (string) ($appointment->get('cabinetId') ?? ''),
                'cabinetName' => (string) ($appointment->get('cabinetName') ?? ''),
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
    private function getPendingActions(?string $clinicId, int $limit): array
    {
        $where = [
            'deleted' => false,
            'status' => AssistantActionProposal::STATUS_PENDING_REVIEW,
        ];

        /** @var iterable<AssistantActionProposal> $proposals */
        $proposals = $this->entityManager
            ->getRDBRepository(AssistantActionProposal::ENTITY_TYPE)
            ->where($where)
            ->order('createdAt', 'ASC')
            ->find();

        $rows = [];
        foreach ($proposals as $proposal) {
            $rows[] = [
                'id' => (string) $proposal->getId(),
                'name' => (string) $proposal->get('name'),
                'source' => (string) ($proposal->get('source') ?? ''),
                'actionType' => (string) ($proposal->get('actionType') ?? ''),
                'riskLevel' => (string) ($proposal->get('riskLevel') ?? ''),
                'status' => (string) ($proposal->get('status') ?? ''),
                'summary' => (string) ($proposal->get('summary') ?? ''),
                'patientId' => (string) ($proposal->get('patientId') ?? ''),
                'patientName' => (string) ($proposal->get('patientName') ?? ''),
                'appointmentId' => (string) ($proposal->get('appointmentId') ?? ''),
                'appointmentName' => (string) ($proposal->get('appointmentName') ?? ''),
                'assignedUserId' => (string) ($proposal->get('assignedUserId') ?? ''),
                'assignedUserName' => (string) ($proposal->get('assignedUserName') ?? ''),
                'createdAt' => (string) ($proposal->get('createdAt') ?? ''),
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
    private function getAssignedTasks(string $userId, int $limit): array
    {
        if ($userId === '') {
            return [];
        }

        try {
            /** @var iterable<Entity> $tasks */
            $tasks = $this->entityManager
                ->getRDBRepository('Task')
                ->where([
                    'deleted' => false,
                    'assignedUserId' => $userId,
                    'status!=' => ['Completed', 'Canceled'],
                ])
                ->order('dateEnd', 'ASC')
                ->find();
        } catch (\Throwable) {
            return [];
        }

        $rows = [];
        foreach ($tasks as $task) {
            $rows[] = [
                'id' => (string) $task->getId(),
                'name' => (string) ($task->get('name') ?? ''),
                'status' => (string) ($task->get('status') ?? ''),
                'priority' => (string) ($task->get('priority') ?? ''),
                'dateEnd' => (string) ($task->get('dateEnd') ?? ''),
                'parentType' => (string) ($task->get('parentType') ?? ''),
                'parentId' => (string) ($task->get('parentId') ?? ''),
                'parentName' => (string) ($task->get('parentName') ?? ''),
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
    private function getLowStockAlerts(?string $clinicId, int $limit): array
    {
        $where = [
            'deleted' => false,
            'status' => LowStockAlert::STATUS_OPEN,
        ];

        if ($clinicId) {
            $where['clinicId'] = $clinicId;
        }

        /** @var iterable<LowStockAlert> $alerts */
        $alerts = $this->entityManager
            ->getRDBRepository(LowStockAlert::ENTITY_TYPE)
            ->where($where)
            ->order('level', 'DESC')
            ->find();

        $rows = [];
        foreach ($alerts as $alert) {
            $rows[] = [
                'id' => (string) $alert->getId(),
                'name' => (string) ($alert->get('name') ?? ''),
                'level' => (string) ($alert->get('level') ?? ''),
                'status' => (string) ($alert->get('status') ?? ''),
                'materialId' => (string) ($alert->get('materialId') ?? ''),
                'materialName' => (string) ($alert->get('materialName') ?? ''),
                'currentStock' => round((float) ($alert->get('currentStock') ?? 0.0), 3),
                'threshold' => round((float) ($alert->get('threshold') ?? 0.0), 3),
                'raisedAt' => (string) ($alert->get('raisedAt') ?? ''),
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
    private function getWeeklyWorkload(DateTimeImmutable $todayStart, ?string $clinicId): array
    {
        $to = $todayStart->modify('+7 days');
        $where = [
            'deleted' => false,
            'dateStart>=' => $todayStart->format('Y-m-d H:i:s'),
            'dateStart<' => $to->format('Y-m-d H:i:s'),
            'status' => [
                Appointment::STATUS_PLANNED,
                Appointment::STATUS_RESCHEDULED,
                Appointment::STATUS_ARRIVED,
                Appointment::STATUS_IN_PROGRESS,
            ],
        ];

        if ($clinicId) {
            $where['clinicId'] = $clinicId;
        }

        $rows = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $todayStart->modify("+{$i} days")->format('Y-m-d');
            $rows[$day] = [
                'date' => $day,
                'appointmentCount' => 0,
                'arrivedCount' => 0,
                'inProgressCount' => 0,
            ];
        }

        /** @var iterable<Appointment> $appointments */
        $appointments = $this->entityManager
            ->getRDBRepository(Appointment::ENTITY_TYPE)
            ->where($where)
            ->order('dateStart', 'ASC')
            ->find();

        foreach ($appointments as $appointment) {
            $day = substr((string) $appointment->getDateStart(), 0, 10);

            if (!isset($rows[$day])) {
                continue;
            }

            $rows[$day]['appointmentCount']++;

            if ($appointment->getStatus() === Appointment::STATUS_ARRIVED) {
                $rows[$day]['arrivedCount']++;
            }

            if ($appointment->getStatus() === Appointment::STATUS_IN_PROGRESS) {
                $rows[$day]['inProgressCount']++;
            }
        }

        return array_values($rows);
    }
}
