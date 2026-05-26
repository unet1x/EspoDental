<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

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
            $rows[] = [
                'id' => (string) $patient->getId(),
                'name' => $this->buildPersonName($patient),
                'phone' => (string) ($patient->get('phone') ?? ''),
                'emailAddress' => (string) ($patient->get('emailAddress') ?? ''),
                'cardNumber' => (string) ($patient->get('cardNumber') ?? ''),
                'status' => (string) ($patient->get('status') ?? ''),
                'balance' => round((float) ($patient->get('balance') ?? 0.0), 2),
                'isChild' => (bool) ($patient->get('isChild') ?? false),
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
            'isChild' => (bool) ($patient->get('isChild') ?? false),
            'balance' => round((float) ($patient->get('balance') ?? 0.0), 2),
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
            'lastQuestionnaireAt' => (string) ($patient->get('lastQuestionnaireAt') ?? ''),
            'questionnaireExpired' => (bool) ($patient->get('questionnaireExpired') ?? true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getToothChartSummary(string $patientId): array
    {
        return [
            'snapshotCount' => $this->countByPatient(ToothChartSnapshot::ENTITY_TYPE, $patientId),
            'latestSnapshotId' => $this->latestId(ToothChartSnapshot::ENTITY_TYPE, $patientId, 'recordedAt'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getClinicalHistorySummary(string $patientId): array
    {
        return [
            'futureAppointmentCount' => $this->countByParent(
                Appointment::ENTITY_TYPE,
                Patient::ENTITY_TYPE,
                $patientId
            ),
            'visitCount' => $this->countByPatient(Visit::ENTITY_TYPE, $patientId),
            'latestVisitId' => $this->latestId(Visit::ENTITY_TYPE, $patientId, 'startedAt'),
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getFinanceSummary(string $patientId): array
    {
        return [
            'openInvoiceCount' => $this->entityManager
                ->getRDBRepository(Invoice::ENTITY_TYPE)
                ->where([
                    'deleted' => false,
                    'patientId' => $patientId,
                    'status' => [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIAL_PAID],
                ])
                ->count(),
            'paymentCount' => $this->countByPatient('Payment', $patientId),
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
