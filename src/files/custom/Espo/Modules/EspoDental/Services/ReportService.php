<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\Invoice;
use Espo\Modules\EspoDental\Entities\LowStockAlert;
use Espo\Modules\EspoDental\Entities\Payment;
use Espo\Modules\EspoDental\Entities\Visit;
use Espo\Modules\EspoDental\Entities\VisitServiceLine;

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

    /**
     * @return array{
     *     dateFrom: string,
     *     dateTo: string,
     *     rows: list<array{
     *         doctorId: string,
     *         doctorName: string,
     *         visitCount: int,
     *         serviceLineCount: int,
     *         grossAmount: float,
     *         averageVisitAmount: float
     *     }>
     * }
     */
    public function getDoctorProductivity(?string $dateFrom = null, ?string $dateTo = null, int $limit = 10): array
    {
        $period = $this->normalizePeriod($dateFrom, $dateTo);
        $limit = max(1, min(50, $limit));

        $visits = $this->entityManager
            ->getRDBRepository(Visit::ENTITY_TYPE)
            ->where([
                'status' => Visit::STATUS_FINISHED,
                'startedAt>=' => $period['from'],
                'startedAt<' => $period['to'],
                'deleted' => false,
            ])
            ->find();

        $rows = [];
        $visitDoctorMap = [];
        $visitIds = [];

        foreach ($visits as $visit) {
            $doctorId = (string) ($visit->get('doctorId') ?? '');

            if ($doctorId === '') {
                continue;
            }

            $visitId = (string) $visit->getId();
            $visitIds[] = $visitId;
            $visitDoctorMap[$visitId] = $doctorId;

            if (!isset($rows[$doctorId])) {
                $rows[$doctorId] = [
                    'doctorId' => $doctorId,
                    'doctorName' => (string) ($visit->get('doctorName') ?: $doctorId),
                    'visitCount' => 0,
                    'serviceLineCount' => 0,
                    'grossAmount' => 0.0,
                    'averageVisitAmount' => 0.0,
                ];
            }

            $rows[$doctorId]['visitCount']++;
            $rows[$doctorId]['grossAmount'] += round((float) ($visit->get('totalAmount') ?? 0.0), 2);
        }

        if ($visitIds !== []) {
            $this->applyServiceLineCounts($rows, $visitDoctorMap, $visitIds);
        }

        foreach ($rows as &$row) {
            $row['grossAmount'] = round((float) $row['grossAmount'], 2);
            $row['averageVisitAmount'] = $row['visitCount'] > 0
                ? round($row['grossAmount'] / $row['visitCount'], 2)
                : 0.0;
        }
        unset($row);

        usort($rows, static function (array $a, array $b): int {
            return [$b['grossAmount'], $b['visitCount'], $a['doctorName']]
                <=> [$a['grossAmount'], $a['visitCount'], $b['doctorName']];
        });

        return [
            'dateFrom' => $period['from'],
            'dateTo' => $period['to'],
            'rows' => array_slice(array_values($rows), 0, $limit),
        ];
    }

    /**
     * @param array<string, array{
     *     doctorId: string,
     *     doctorName: string,
     *     visitCount: int,
     *     serviceLineCount: int,
     *     grossAmount: float,
     *     averageVisitAmount: float
     * }> $rows
     * @param array<string, string> $visitDoctorMap
     * @param list<string> $visitIds
     */
    private function applyServiceLineCounts(array &$rows, array $visitDoctorMap, array $visitIds): void
    {
        $serviceLines = $this->entityManager
            ->getRDBRepository(VisitServiceLine::ENTITY_TYPE)
            ->where([
                'visitId' => $visitIds,
                'deleted' => false,
            ])
            ->find();

        foreach ($serviceLines as $line) {
            $visitId = (string) ($line->get('visitId') ?? '');
            $doctorId = (string) ($line->get('doctorId') ?: ($visitDoctorMap[$visitId] ?? ''));

            if ($doctorId === '' || !isset($rows[$doctorId])) {
                continue;
            }

            $rows[$doctorId]['serviceLineCount']++;
        }
    }

    /**
     * @return array{from: string, to: string}
     */
    private function normalizePeriod(?string $dateFrom, ?string $dateTo): array
    {
        $defaultFrom = new DateTimeImmutable('first day of this month 00:00:00');
        $defaultTo = $defaultFrom->modify('+1 month');

        return [
            'from' => $this->normalizeDateTime($dateFrom, $defaultFrom, false),
            'to' => $this->normalizeDateTime($dateTo, $defaultTo, true),
        ];
    }

    private function normalizeDateTime(?string $value, DateTimeImmutable $fallback, bool $exclusiveEnd): string
    {
        if ($value === null || trim($value) === '') {
            return $fallback->format('Y-m-d H:i:s');
        }

        $value = trim($value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($date instanceof DateTimeImmutable) {
            return $exclusiveEnd
                ? $date->modify('+1 day')->format('Y-m-d H:i:s')
                : $date->format('Y-m-d 00:00:00');
        }

        try {
            return (new DateTimeImmutable($value))->format('Y-m-d H:i:s');
        } catch (\Exception) {
            return $fallback->format('Y-m-d H:i:s');
        }
    }
}
