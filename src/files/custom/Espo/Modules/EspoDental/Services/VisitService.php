<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\Appointment;
use Espo\Modules\EspoDental\Entities\Patient;
use Espo\Modules\EspoDental\Entities\ToothChartSnapshot;
use Espo\Modules\EspoDental\Entities\Visit;
use Espo\Modules\EspoDental\Entities\VisitServiceLine;
use Espo\Modules\EspoDental\Services\InvoiceService;
use Espo\Modules\EspoDental\Services\StockService;

class VisitService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly InvoiceService $invoiceService,
        private readonly StockService $stockService
    ) {
    }

    /**
     * @return array{visitId: string, total: float, lineCount: int, invoiceId: ?string}
     */
    public function finishVisit(string $visitId): array
    {
        /** @var Visit|null $visit */
        $visit = $this->entityManager->getEntityById(Visit::ENTITY_TYPE, $visitId);

        if (!$visit) {
            throw new NotFound('Visit not found');
        }

        if (!in_array($visit->getStatus(), [Visit::STATUS_IN_PROGRESS, Visit::STATUS_FINISHED], true)) {
            throw new Conflict('Visit is not in progress');
        }

        $total = $this->recalculateTotal($visit);
        $lineCount = $this->countLines($visit);

        $invoiceId = null;
        if ($lineCount > 0) {
            $invoice = $this->invoiceService->buildFromVisit($visit);
            $invoiceId = (string) $invoice->getId();
        }

        $this->stockService->consumeForVisit($visit);

        $this->markVisitFinished($visit, $total);
        $this->markAppointmentFinished($visit);

        return [
            'visitId' => (string) $visit->getId(),
            'total' => $total,
            'lineCount' => $lineCount,
            'invoiceId' => $invoiceId,
        ];
    }

    private function markVisitFinished(Visit $visit, float $total): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        if (!$visit->get('finishedAt')) {
            $visit->set('finishedAt', $now);
        }

        $visit->set('status', Visit::STATUS_FINISHED);
        $visit->set('totalAmount', $total);
        $this->entityManager->saveEntity($visit);
    }

    private function markAppointmentFinished(Visit $visit): void
    {
        if ($visit->getAppointmentId()) {
            /** @var Appointment|null $appointment */
            $appointment = $this->entityManager->getEntityById(
                Appointment::ENTITY_TYPE,
                $visit->getAppointmentId()
            );
            if ($appointment && $appointment->getStatus() === Appointment::STATUS_IN_PROGRESS) {
                $appointment->set('status', Appointment::STATUS_FINISHED);
                $this->entityManager->saveEntity($appointment, [
                    'skipConflictCheck' => true,
                    'espodentalAllowAppointmentSystemStatus' => true,
                ]);
            }
        }
    }

    public function recalculateTotal(Visit $visit): float
    {
        /** @var iterable<VisitServiceLine> $lines */
        $lines = $this->entityManager
            ->getRDBRepository(VisitServiceLine::ENTITY_TYPE)
            ->where(['visitId' => $visit->getId()])
            ->find();

        $total = 0.0;
        foreach ($lines as $line) {
            $total += $line->getAmount();
        }
        return round($total, 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function getToothChartData(string $visitId): array
    {
        /** @var Visit|null $visit */
        $visit = $this->entityManager->getEntityById(Visit::ENTITY_TYPE, $visitId);

        if (!$visit) {
            throw new NotFound('Visit not found');
        }

        /** @var ToothChartSnapshot|null $snapshot */
        $snapshot = $this->entityManager
            ->getRDBRepository(ToothChartSnapshot::ENTITY_TYPE)
            ->where(['visitId' => $visit->getId()])
            ->order('recordedAt', 'DESC')
            ->findOne();

        if (!$snapshot) {
            $snapshot = $this->createToothChartSnapshot($visit);
        }

        if (!$snapshot) {
            return ['id' => null];
        }

        return [
            'id' => $snapshot->getId(),
            'dentitionType' => $snapshot->get('dentitionType'),
            'teeth' => $snapshot->getTeeth(),
            'recordedAt' => $snapshot->get('recordedAt'),
        ];
    }

    private function createToothChartSnapshot(Visit $visit): ?ToothChartSnapshot
    {
        if (!$visit->getPatientId()) {
            return null;
        }

        /** @var Patient|null $patient */
        $patient = $this->entityManager->getEntityById(Patient::ENTITY_TYPE, $visit->getPatientId());

        if (!$patient) {
            return null;
        }

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
        $snapshot->set('recordedAt', (new DateTimeImmutable())->format('Y-m-d H:i:s'));

        $this->entityManager->saveEntity($snapshot);

        return $snapshot;
    }

    private function countLines(Visit $visit): int
    {
        return (int) $this->entityManager
            ->getRDBRepository(VisitServiceLine::ENTITY_TYPE)
            ->where(['visitId' => $visit->getId()])
            ->count();
    }
}
