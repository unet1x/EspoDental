<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\Appointment;
use Espo\Modules\EspoDental\Entities\Invoice;
use Espo\Modules\EspoDental\Entities\Patient;
use Espo\Modules\EspoDental\Entities\ToothChartSnapshot;
use Espo\Modules\EspoDental\Entities\Visit;
use Espo\ORM\Entity;

class PatientWorkspaceService
{
    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getWorkspace(?string $query = null, ?string $selectedId = null, int $limit = 20): array
    {
        $limit = max(1, min(50, $limit));
        $patients = $this->getPatientList(trim((string) $query), $limit);
        $selectedId = $selectedId ?: ($patients[0]['id'] ?? null);

        return [
            'query' => trim((string) $query),
            'patients' => $patients,
            'selectedPatientId' => $selectedId,
            'selectedPatient' => $selectedId ? $this->getSelectedPatient($selectedId) : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getPatientList(string $query, int $limit): array
    {
        $where = ['deleted' => false];

        if ($query !== '') {
            $where['OR'] = [
                ['lastName*' => $query . '%'],
                ['firstName*' => $query . '%'],
                ['phone*' => '%' . $query . '%'],
                ['emailAddress*' => '%' . $query . '%'],
                ['cardNumber*' => '%' . $query . '%'],
            ];
        }

        /** @var iterable<Patient> $patients */
        $patients = $this->entityManager
            ->getRDBRepository(Patient::ENTITY_TYPE)
            ->where($where)
            ->order('lastName', 'ASC')
            ->find();

        $rows = [];
        foreach ($patients as $patient) {
            $nextAppointment = $this->getNextAppointment((string) $patient->getId());

            $rows[] = [
                'id' => (string) $patient->getId(),
                'name' => $this->buildPersonName($patient),
                'phone' => (string) ($patient->get('phone') ?? ''),
                'emailAddress' => (string) ($patient->get('emailAddress') ?? ''),
                'cardNumber' => (string) ($patient->get('cardNumber') ?? ''),
                'status' => (string) ($patient->get('status') ?? ''),
                'age' => $this->calculateAge((string) ($patient->get('dateOfBirth') ?? '')),
                'preferredChannel' => (string) ($patient->get('preferredChannel') ?? ''),
                'vip' => (bool) ($patient->get('vip') ?? false),
                'restrictions' => (bool) ($patient->get('restrictions') ?? false),
                'questionnaireExpired' => (bool) ($patient->get('questionnaireExpired') ?? true),
                'questionnaireHasAlerts' => (bool) ($patient->get('questionnaireHasAlerts') ?? false),
                'balance' => round((float) ($patient->get('balance') ?? 0.0), 2),
                'isChild' => (bool) ($patient->get('isChild') ?? false),
                'nextAppointment' => $nextAppointment,
                'alerts' => $this->buildPatientAlerts($patient),
            ];

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getSelectedPatient(string $patientId): ?array
    {
        /** @var Patient|null $patient */
        $patient = $this->entityManager->getEntityById(Patient::ENTITY_TYPE, $patientId);

        if (!$patient) {
            return null;
        }

        return [
            'id' => (string) $patient->getId(),
            'name' => $this->buildPersonName($patient),
            'cardNumber' => (string) ($patient->get('cardNumber') ?? ''),
            'phone' => (string) ($patient->get('phone') ?? ''),
            'emailAddress' => (string) ($patient->get('emailAddress') ?? ''),
            'dateOfBirth' => (string) ($patient->get('dateOfBirth') ?? ''),
            'status' => (string) ($patient->get('status') ?? ''),
            'age' => $this->calculateAge((string) ($patient->get('dateOfBirth') ?? '')),
            'preferredChannel' => (string) ($patient->get('preferredChannel') ?? ''),
            'vip' => (bool) ($patient->get('vip') ?? false),
            'restrictions' => (bool) ($patient->get('restrictions') ?? false),
            'questionnaireExpired' => (bool) ($patient->get('questionnaireExpired') ?? true),
            'questionnaireHasAlerts' => (bool) ($patient->get('questionnaireHasAlerts') ?? false),
            'isChild' => (bool) ($patient->get('isChild') ?? false),
            'balance' => round((float) ($patient->get('balance') ?? 0.0), 2),
            'nextAppointment' => $this->getNextAppointment($patientId),
            'alerts' => $this->buildPatientAlerts($patient),
            'quickActions' => ['bookAppointment', 'uploadFile'],
            'tabs' => [
                'basicData' => $this->getBasicData($patient),
                'toothChart' => $this->getToothChartSummary($patientId),
                'clinicalHistory' => $this->getClinicalHistorySummary($patientId),
                'files' => $this->getFilesSummary($patientId),
                'finance' => $this->getFinanceSummary($patientId),
                'family' => $this->getFamilySummary($patient),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getBasicData(Patient $patient): array
    {
        return [
            'lastName' => (string) ($patient->get('lastName') ?? ''),
            'firstName' => (string) ($patient->get('firstName') ?? ''),
            'middleName' => (string) ($patient->get('middleName') ?? ''),
            'gender' => (string) ($patient->get('gender') ?? ''),
            'dateOfBirth' => (string) ($patient->get('dateOfBirth') ?? ''),
            'phone' => (string) ($patient->get('phone') ?? ''),
            'emailAddress' => (string) ($patient->get('emailAddress') ?? ''),
            'preferredChannel' => (string) ($patient->get('preferredChannel') ?? ''),
            'lastQuestionnaireAt' => (string) ($patient->get('lastQuestionnaireAt') ?? ''),
            'questionnaireExpired' => (bool) ($patient->get('questionnaireExpired') ?? true),
            'questionnaireHasAlerts' => (bool) ($patient->get('questionnaireHasAlerts') ?? false),
            'vip' => (bool) ($patient->get('vip') ?? false),
            'restrictions' => (bool) ($patient->get('restrictions') ?? false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getToothChartSummary(string $patientId): array
    {
        $recentSnapshots = $this->getRecentToothChartSnapshots($patientId);

        return [
            'snapshotCount' => $this->countByPatient(ToothChartSnapshot::ENTITY_TYPE, $patientId),
            'latestSnapshotId' => $recentSnapshots[0]['id'] ?? null,
            'currentSnapshot' => $recentSnapshots[0] ?? null,
            'recentSnapshots' => $recentSnapshots,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getRecentToothChartSnapshots(string $patientId, int $limit = 5): array
    {
        /** @var iterable<ToothChartSnapshot> $snapshots */
        $snapshots = $this->entityManager
            ->getRDBRepository(ToothChartSnapshot::ENTITY_TYPE)
            ->where([
                'deleted' => false,
                'patientId' => $patientId,
            ])
            ->order('recordedAt', 'DESC')
            ->find();

        $rows = [];
        foreach ($snapshots as $snapshot) {
            $rows[] = $this->summarizeToothChartSnapshot($snapshot);

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function summarizeToothChartSnapshot(ToothChartSnapshot $snapshot): array
    {
        $teeth = $snapshot->getTeeth();

        return [
            'id' => (string) $snapshot->getId(),
            'name' => (string) ($snapshot->get('name') ?? ''),
            'recordedAt' => (string) ($snapshot->get('recordedAt') ?? ''),
            'dentitionType' => (string) ($snapshot->get('dentitionType') ?? ToothChartSnapshot::DENTITION_ADULT),
            'visitId' => (string) ($snapshot->get('visitId') ?? ''),
            'visitName' => (string) ($snapshot->get('visitName') ?? ''),
            'doctorName' => (string) ($snapshot->get('doctorName') ?? ''),
            'notes' => (string) ($snapshot->get('notes') ?? ''),
            'annotatedTeeth' => $this->countAnnotatedTeeth($teeth),
            'summary' => $this->summarizeTeeth($teeth),
        ];
    }

    /**
     * @param array<string, mixed> $teeth
     * @return list<array{tooth: string, surface: string, condition: string, note: string}>
     */
    private function summarizeTeeth(array $teeth): array
    {
        $rows = [];
        $surfaces = ['o', 'm', 'd', 'b', 'l'];

        foreach ($teeth as $number => $state) {
            if (is_object($state)) {
                $state = (array) $state;
            }
            if (!is_array($state)) {
                continue;
            }

            $wholeCondition = (string) ($state['c'] ?? 'healthy');
            if ($wholeCondition !== '' && $wholeCondition !== 'healthy') {
                $rows[] = [
                    'tooth' => (string) $number,
                    'surface' => '',
                    'condition' => $wholeCondition,
                    'note' => (string) ($state['n'] ?? ''),
                ];
            }

            $surfaceStates = $state['surfaces'] ?? [];
            if (is_object($surfaceStates)) {
                $surfaceStates = (array) $surfaceStates;
            }
            if (!is_array($surfaceStates)) {
                $surfaceStates = [];
            }

            foreach ($surfaces as $surface) {
                $surfaceState = $surfaceStates[$surface] ?? null;
                if (is_object($surfaceState)) {
                    $surfaceState = (array) $surfaceState;
                }
                if (!is_array($surfaceState)) {
                    continue;
                }

                $condition = (string) ($surfaceState['c'] ?? 'healthy');
                $note = (string) ($surfaceState['n'] ?? '');
                if ($condition === 'healthy' && $note === '') {
                    continue;
                }

                $rows[] = [
                    'tooth' => (string) $number,
                    'surface' => strtoupper($surface),
                    'condition' => $condition,
                    'note' => $note,
                ];

                if (count($rows) >= 6) {
                    return $rows;
                }
            }

            if (count($rows) >= 6) {
                return $rows;
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $teeth
     */
    private function countAnnotatedTeeth(array $teeth): int
    {
        $count = 0;

        foreach ($teeth as $value) {
            if (is_array($value) && $value !== []) {
                $count++;
                continue;
            }

            if (is_object($value) && (array) $value !== []) {
                $count++;
                continue;
            }

            if (is_string($value) && trim($value) !== '') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    private function getClinicalHistorySummary(string $patientId): array
    {
        return [
            'nextAppointment' => $this->getNextAppointment($patientId),
            'futureAppointmentCount' => $this->countByParent(
                Appointment::ENTITY_TYPE,
                Patient::ENTITY_TYPE,
                $patientId
            ),
            'visitCount' => $this->countByPatient(Visit::ENTITY_TYPE, $patientId),
            'latestVisitId' => $this->latestId(Visit::ENTITY_TYPE, $patientId, 'startedAt'),
            'recentAppointments' => $this->getRecentAppointments($patientId),
            'recentVisits' => $this->getRecentVisits($patientId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getFilesSummary(string $patientId): array
    {
        return [
            'visitPhotoCount' => $this->countByPatient('VisitPhoto', $patientId),
            'questionnaireCount' => $this->countByPatient('HealthQuestionnaire', $patientId),
            'recentQuestionnaires' => $this->getRecentQuestionnaires($patientId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getFinanceSummary(string $patientId): array
    {
        $openInvoiceBalance = 0.0;
        $openInvoiceCount = 0;
        /** @var iterable<Invoice> $openInvoices */
        $openInvoices = $this->entityManager
            ->getRDBRepository(Invoice::ENTITY_TYPE)
            ->where([
                'deleted' => false,
                'patientId' => $patientId,
                'status' => [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIAL_PAID],
            ])
            ->find();

        foreach ($openInvoices as $invoice) {
            $openInvoiceCount++;
            $openInvoiceBalance += (float) ($invoice->get('balance') ?? 0.0);
        }

        return [
            'openInvoiceCount' => $openInvoiceCount,
            'openInvoiceBalance' => round($openInvoiceBalance, 2),
            'paymentCount' => $this->countByPatient('Payment', $patientId),
            'openInvoices' => $this->getOpenInvoices($patientId),
            'recentPayments' => $this->getRecentPayments($patientId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getFamilySummary(Patient $patient): array
    {
        return [
            'isChild' => (bool) ($patient->get('isChild') ?? false),
            'parentPatientId' => (string) ($patient->get('parentPatientId') ?? ''),
            'parentPatientName' => (string) ($patient->get('parentPatientName') ?? ''),
            'childCount' => $this->entityManager
                ->getRDBRepository(Patient::ENTITY_TYPE)
                ->where([
                    'deleted' => false,
                    'parentPatientId' => $patient->getId(),
                ])
                ->count(),
        ];
    }

    private function countByPatient(string $entityType, string $patientId): int
    {
        return $this->entityManager
            ->getRDBRepository($entityType)
            ->where(['deleted' => false, 'patientId' => $patientId])
            ->count();
    }

    private function countByParent(string $entityType, string $parentType, string $parentId): int
    {
        return $this->entityManager
            ->getRDBRepository($entityType)
            ->where([
                'deleted' => false,
                'parentType' => $parentType,
                'parentId' => $parentId,
            ])
            ->count();
    }

    private function latestId(string $entityType, string $patientId, string $orderField): ?string
    {
        /** @var Entity|null $entity */
        $entity = $this->entityManager
            ->getRDBRepository($entityType)
            ->where(['deleted' => false, 'patientId' => $patientId])
            ->order($orderField, 'DESC')
            ->findOne();

        return $entity ? (string) $entity->getId() : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getNextAppointment(string $patientId): ?array
    {
        /** @var Appointment|null $appointment */
        $appointment = $this->entityManager
            ->getRDBRepository(Appointment::ENTITY_TYPE)
            ->where([
                'deleted' => false,
                'parentType' => Patient::ENTITY_TYPE,
                'parentId' => $patientId,
                'status' => Appointment::BLOCKING_STATUSES,
                'dateStart>=' => $this->nowUtc(),
            ])
            ->order('dateStart', 'ASC')
            ->findOne();

        if (!$appointment) {
            return null;
        }

        return [
            'id' => (string) $appointment->getId(),
            'name' => (string) ($appointment->get('name') ?? ''),
            'dateStart' => (string) ($appointment->getDateStart() ?? ''),
            'dateEnd' => (string) ($appointment->getDateEnd() ?? ''),
            'status' => (string) ($appointment->getStatus() ?? ''),
            'doctorName' => (string) ($appointment->get('doctorName') ?? ''),
            'cabinetName' => (string) ($appointment->get('cabinetName') ?? ''),
            'serviceName' => (string) ($appointment->get('serviceName') ?? ''),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getRecentAppointments(string $patientId, int $limit = 5): array
    {
        /** @var iterable<Appointment> $appointments */
        $appointments = $this->entityManager
            ->getRDBRepository(Appointment::ENTITY_TYPE)
            ->where([
                'deleted' => false,
                'parentType' => Patient::ENTITY_TYPE,
                'parentId' => $patientId,
            ])
            ->order('dateStart', 'DESC')
            ->find();

        $rows = [];
        foreach ($appointments as $appointment) {
            $rows[] = [
                'entityType' => Appointment::ENTITY_TYPE,
                'id' => (string) $appointment->getId(),
                'label' => (string) ($appointment->get('name') ?: 'Запись'),
                'date' => (string) ($appointment->getDateStart() ?? ''),
                'status' => (string) ($appointment->getStatus() ?? ''),
                'doctorName' => (string) ($appointment->get('doctorName') ?? ''),
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
    private function getRecentVisits(string $patientId, int $limit = 5): array
    {
        /** @var iterable<Visit> $visits */
        $visits = $this->entityManager
            ->getRDBRepository(Visit::ENTITY_TYPE)
            ->where(['deleted' => false, 'patientId' => $patientId])
            ->order('startedAt', 'DESC')
            ->find();

        $rows = [];
        foreach ($visits as $visit) {
            $rows[] = [
                'entityType' => Visit::ENTITY_TYPE,
                'id' => (string) $visit->getId(),
                'label' => (string) ($visit->get('name') ?: 'Прием'),
                'date' => (string) ($visit->get('startedAt') ?? ''),
                'status' => (string) ($visit->get('status') ?? ''),
                'doctorName' => (string) ($visit->get('doctorName') ?? ''),
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
    private function getRecentQuestionnaires(string $patientId, int $limit = 5): array
    {
        /** @var iterable<Entity> $questionnaires */
        $questionnaires = $this->entityManager
            ->getRDBRepository('HealthQuestionnaire')
            ->where(['deleted' => false, 'patientId' => $patientId])
            ->order('filledAt', 'DESC')
            ->find();

        $rows = [];
        foreach ($questionnaires as $questionnaire) {
            $rows[] = [
                'entityType' => 'HealthQuestionnaire',
                'id' => (string) $questionnaire->getId(),
                'label' => (string) ($questionnaire->get('name') ?: 'Анкета'),
                'date' => (string) ($questionnaire->get('filledAt') ?? ''),
                'status' => (bool) ($questionnaire->get('hasAlerts') ?? false) ? 'medical_alert' : 'ok',
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
    private function getOpenInvoices(string $patientId, int $limit = 5): array
    {
        /** @var iterable<Invoice> $invoices */
        $invoices = $this->entityManager
            ->getRDBRepository(Invoice::ENTITY_TYPE)
            ->where([
                'deleted' => false,
                'patientId' => $patientId,
                'status' => [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIAL_PAID],
            ])
            ->order('issuedAt', 'DESC')
            ->find();

        $rows = [];
        foreach ($invoices as $invoice) {
            $rows[] = [
                'entityType' => Invoice::ENTITY_TYPE,
                'id' => (string) $invoice->getId(),
                'label' => (string) ($invoice->get('number') ?: $invoice->get('name') ?: 'Счет'),
                'date' => (string) ($invoice->get('issuedAt') ?? ''),
                'status' => (string) ($invoice->get('status') ?? ''),
                'amount' => round((float) ($invoice->get('balance') ?? 0.0), 2),
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
    private function getRecentPayments(string $patientId, int $limit = 5): array
    {
        /** @var iterable<Entity> $payments */
        $payments = $this->entityManager
            ->getRDBRepository('Payment')
            ->where(['deleted' => false, 'patientId' => $patientId])
            ->order('paidAt', 'DESC')
            ->find();

        $rows = [];
        foreach ($payments as $payment) {
            $rows[] = [
                'entityType' => 'Payment',
                'id' => (string) $payment->getId(),
                'label' => (string) ($payment->get('number') ?: $payment->get('name') ?: 'Платеж'),
                'date' => (string) ($payment->get('paidAt') ?? ''),
                'status' => (string) ($payment->get('status') ?? ''),
                'amount' => round((float) ($payment->get('amount') ?? 0.0), 2),
            ];

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @return list<array{label: string, tone: string, key: string}>
     */
    private function buildPatientAlerts(Patient $patient): array
    {
        $alerts = [];

        if ((bool) ($patient->get('vip') ?? false)) {
            $alerts[] = ['key' => 'vip', 'label' => 'VIP', 'tone' => 'primary'];
        }
        if ((bool) ($patient->get('restrictions') ?? false)) {
            $alerts[] = ['key' => 'restrictions', 'label' => 'ограничения', 'tone' => 'danger'];
        }
        if ((bool) ($patient->get('questionnaireHasAlerts') ?? false)) {
            $alerts[] = ['key' => 'questionnaireHasAlerts', 'label' => 'мед. алерт', 'tone' => 'danger'];
        }
        if ((bool) ($patient->get('questionnaireExpired') ?? true)) {
            $alerts[] = ['key' => 'questionnaireExpired', 'label' => 'анкета устарела', 'tone' => 'warning'];
        }

        $balance = round((float) ($patient->get('balance') ?? 0.0), 2);
        if ($balance < 0.0) {
            $alerts[] = ['key' => 'debt', 'label' => 'долг ' . abs($balance), 'tone' => 'danger'];
        } elseif ($balance > 0.0) {
            $alerts[] = ['key' => 'credit', 'label' => 'аванс ' . $balance, 'tone' => 'success'];
        }

        if ((bool) ($patient->get('isChild') ?? false)) {
            $alerts[] = ['key' => 'child', 'label' => 'ребенок', 'tone' => 'info'];
        }

        return $alerts;
    }

    private function calculateAge(string $dateOfBirth): ?int
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOfBirth)) {
            return null;
        }

        try {
            $birthDate = new DateTimeImmutable($dateOfBirth . ' 00:00:00');
            $today = new DateTimeImmutable('today');
        } catch (\Exception) {
            return null;
        }

        if ($birthDate > $today) {
            return null;
        }

        return (int) $birthDate->diff($today)->y;
    }

    private function nowUtc(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }

    private function buildPersonName(Entity $entity): string
    {
        $parts = array_filter([
            trim((string) $entity->get('lastName')),
            trim((string) $entity->get('firstName')),
            trim((string) $entity->get('middleName')),
        ]);

        $name = trim(implode(' ', $parts));

        return $name !== '' ? $name : (string) ($entity->get('name') ?? '');
    }
}
