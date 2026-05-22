<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\Patient;
use Espo\Modules\EspoDental\Entities\ToothChartSnapshot;

class PatientToothChartService
{
    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    /**
     * @return array{patientId: string, toothCharts: list<array<string, mixed>>}
     */
    public function getPatientToothCharts(
        string $patientId,
        bool $includeToothCharts = true,
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
            'toothCharts' => $includeToothCharts ? $this->getToothCharts($patientId, $limit) : [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getToothCharts(string $patientId, int $limit): array
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
            $rows[] = [
                'id' => (string) $snapshot->getId(),
                'name' => (string) ($snapshot->get('name') ?? ''),
                'recordedAt' => (string) ($snapshot->get('recordedAt') ?? ''),
                'dentitionType' => (string) ($snapshot->get('dentitionType') ?? ''),
                'visitId' => $snapshot->get('visitId'),
                'visitName' => $snapshot->get('visitName'),
                'doctorId' => $snapshot->get('doctorId'),
                'doctorName' => $snapshot->get('doctorName'),
                'notes' => (string) ($snapshot->get('notes') ?? ''),
                'teethCount' => $this->countAnnotatedTeeth($snapshot),
            ];

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    private function countAnnotatedTeeth(ToothChartSnapshot $snapshot): int
    {
        $count = 0;

        foreach ($snapshot->getTeeth() as $value) {
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
}
