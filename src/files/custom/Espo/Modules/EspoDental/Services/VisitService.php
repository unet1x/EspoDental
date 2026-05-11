<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\Appointment;
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

        if ($visit->getStatus() !== Visit::STATUS_IN_PROGRESS) {
            throw new Conflict('Visit is not in progress');
        }

        $total = $this->recalculateTotal($visit);

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $visit->set('status', Visit::STATUS_FINISHED);
        $visit->set('finishedAt', $now);
        $visit->set('totalAmount', $total);
        $this->entityManager->saveEntity($visit);

        if ($visit->getAppointmentId()) {
            /** @var Appointment|null $appointment */
            $appointment = $this->entityManager->getEntityById(
                Appointment::ENTITY_TYPE,
                $visit->getAppointmentId()
            );
            if ($appointment && $appointment->getStatus() === Appointment::STATUS_IN_PROGRESS) {
                $appointment->set('status', Appointment::STATUS_FINISHED);
                $this->entityManager->saveEntity($appointment, ['skipConflictCheck' => true]);
            }
        }

        $invoiceId = null;
        if ($this->countLines($visit) > 0) {
            $invoice = $this->invoiceService->buildFromVisit($visit);
            $invoiceId = (string) $invoice->getId();
        }

        $this->stockService->consumeForVisit($visit);

        return [
            'visitId' => (string) $visit->getId(),
            'total' => $total,
            'lineCount' => $this->countLines($visit),
            'invoiceId' => $invoiceId,
        ];
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

    private function countLines(Visit $visit): int
    {
        return (int) $this->entityManager
            ->getRDBRepository(VisitServiceLine::ENTITY_TYPE)
            ->where(['visitId' => $visit->getId()])
            ->count();
    }
}
