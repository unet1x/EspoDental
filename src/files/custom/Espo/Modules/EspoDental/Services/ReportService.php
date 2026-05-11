<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\Invoice;
use Espo\Modules\EspoDental\Entities\LowStockAlert;
use Espo\Modules\EspoDental\Entities\Payment;

class ReportService
{
    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    /**
     * @return list<array{label: string, value: float, year: int, month: int}>
     */
    public function getMonthlyRevenue(int $monthsBack = 12): array
    {
        $now = new DateTimeImmutable('first day of this month 00:00');
        $rows = [];
        for ($i = $monthsBack - 1; $i >= 0; $i--) {
            $from = $now->modify("-{$i} months");
            $to = $from->modify('+1 month');
            $sum = $this->sumPaymentsBetween($from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s'));
            $rows[] = [
                'label' => $from->format('Y-m'),
                'value' => $sum,
                'year' => (int) $from->format('Y'),
                'month' => (int) $from->format('m'),
            ];
        }
        return $rows;
    }

    private function sumPaymentsBetween(string $from, string $to): float
    {
        $qb = $this->entityManager
            ->getQueryBuilder()
            ->select(['SUM:amount'])
            ->from(Payment::ENTITY_TYPE)
            ->where([
                'paidAt>=' => $from,
                'paidAt<' => $to,
                'direction' => Payment::DIRECTION_IN,
                'status' => Payment::STATUS_COMPLETED,
                'deleted' => false,
            ])
            ->build();
        $row = $this->entityManager->getQueryExecutor()->execute($qb)->fetch();
        if (!$row) {
            return 0.0;
        }
        $val = $row['SUM:amount'] ?? array_values($row)[0] ?? 0;
        return (float) $val;
    }

    /**
     * @return array{open: int, overdue: int, paidThisMonth: float}
     */
    public function getInvoiceSummary(): array
    {
        $open = $this->entityManager
            ->getRDBRepository(Invoice::ENTITY_TYPE)
            ->where(['status' => [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIAL_PAID]])
            ->count();

        $overdue = $this->entityManager
            ->getRDBRepository(Invoice::ENTITY_TYPE)
            ->where([
                'status' => [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIAL_PAID],
                'dueDate<' => (new DateTimeImmutable('today'))->format('Y-m-d'),
            ])
            ->count();

        $from = (new DateTimeImmutable('first day of this month 00:00'))->format('Y-m-d H:i:s');
        $to = (new DateTimeImmutable('first day of next month 00:00'))->format('Y-m-d H:i:s');
        $paidThisMonth = $this->sumPaymentsBetween($from, $to);

        return ['open' => $open, 'overdue' => $overdue, 'paidThisMonth' => $paidThisMonth];
    }

    /**
     * @return array{open: int, critical: int}
     */
    public function getLowStockSummary(): array
    {
        $open = $this->entityManager
            ->getRDBRepository(LowStockAlert::ENTITY_TYPE)
            ->where(['status' => LowStockAlert::STATUS_OPEN])
            ->count();

        $critical = $this->entityManager
            ->getRDBRepository(LowStockAlert::ENTITY_TYPE)
            ->where([
                'status' => LowStockAlert::STATUS_OPEN,
                'level' => [LowStockAlert::LEVEL_CRITICAL, LowStockAlert::LEVEL_OUT],
            ])
            ->count();

        return ['open' => $open, 'critical' => $critical];
    }
}
