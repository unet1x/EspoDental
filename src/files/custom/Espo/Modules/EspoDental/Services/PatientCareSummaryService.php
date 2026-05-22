<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\OrthodonticCard;
use Espo\Modules\EspoDental\Entities\Patient;
use Espo\ORM\Entity;

class PatientCareSummaryService
{
    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    /**
     * @return array{
     *     patientId: string,
     *     family: array<string, mixed>,
     *     orthodonticCards: list<array<string, mixed>>
     * }
     */
    public function getPatientCareSummary(
        string $patientId,
        bool $includeOrthodontics = true,
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
            'family' => $this->getFamilySummary($patient, $limit),
            'orthodonticCards' => $includeOrthodontics ? $this->getOrthodonticCards($patientId, $limit) : [],
        ];
    }

    /**
     * @return array{
     *     isChild: bool,
     *     parentPatient: ?array<string, mixed>,
     *     manualGuardian: array<string, mixed>,
     *     childPatients: list<array<string, mixed>>
     * }
     */
    private function getFamilySummary(Patient $patient, int $limit): array
    {
        return [
            'isChild' => $patient->isChild(),
            'parentPatient' => $this->getParentPatient($patient),
            'manualGuardian' => [
                'relation' => (string) ($patient->get('parentRelation') ?? ''),
                'lastName' => (string) ($patient->get('parentLastName') ?? ''),
                'firstName' => (string) ($patient->get('parentFirstName') ?? ''),
                'middleName' => (string) ($patient->get('parentMiddleName') ?? ''),
                'phone' => (string) ($patient->get('parentPhone') ?? ''),
            ],
            'childPatients' => $this->getChildPatients((string) $patient->getId(), $limit),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getParentPatient(Patient $patient): ?array
    {
        $parentPatientId = $patient->getParentPatientId();
        if (!$parentPatientId) {
            return null;
        }

        /** @var Patient|null $parent */
        $parent = $this->entityManager->getEntityById(Patient::ENTITY_TYPE, $parentPatientId);
        if (!$parent) {
            return null;
        }

        return $this->patientRow($parent);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getChildPatients(string $patientId, int $limit): array
    {
        /** @var iterable<Patient> $patients */
        $patients = $this->entityManager
            ->getRDBRepository(Patient::ENTITY_TYPE)
            ->where([
                'deleted' => false,
                'parentPatientId' => $patientId,
            ])
            ->order('lastName', 'ASC')
            ->find();

        $rows = [];
        foreach ($patients as $patient) {
            $rows[] = $this->patientRow($patient);

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getOrthodonticCards(string $patientId, int $limit): array
    {
        /** @var iterable<OrthodonticCard> $cards */
        $cards = $this->entityManager
            ->getRDBRepository(OrthodonticCard::ENTITY_TYPE)
            ->where([
                'deleted' => false,
                'patientId' => $patientId,
            ])
            ->order('dateOpen', 'DESC')
            ->find();

        $rows = [];
        foreach ($cards as $card) {
            $rows[] = [
                'id' => (string) $card->getId(),
                'name' => (string) $card->get('name'),
                'cardNumber' => (string) ($card->getCardNumber() ?? ''),
                'status' => $card->getStatus(),
                'dateOpen' => (string) ($card->get('dateOpen') ?? ''),
                'dateClose' => (string) ($card->get('dateClose') ?? ''),
                'doctorId' => $card->get('doctorId'),
                'doctorName' => $card->get('doctorName'),
                'malocclusionClass' => (string) ($card->get('malocclusionClass') ?? ''),
                'apparatusType' => (string) ($card->get('apparatusType') ?? ''),
                'isActive' => $card->isActive(),
            ];

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function patientRow(Patient $patient): array
    {
        return [
            'id' => (string) $patient->getId(),
            'name' => $this->buildPatientName($patient),
            'phone' => (string) ($patient->get('phone') ?? ''),
            'dateOfBirth' => (string) ($patient->get('dateOfBirth') ?? ''),
            'isChild' => $patient->isChild(),
            'status' => (string) ($patient->getStatus() ?? ''),
        ];
    }

    private function buildPatientName(Entity $patient): string
    {
        $parts = array_filter([
            trim((string) $patient->get('lastName')),
            trim((string) $patient->get('firstName')),
            trim((string) $patient->get('middleName')),
        ]);

        $fullName = trim(implode(' ', $parts));

        return $fullName !== '' ? $fullName : trim((string) $patient->get('name'));
    }
}
