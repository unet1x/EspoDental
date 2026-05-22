<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\Appointment;
use Espo\Modules\EspoDental\Entities\Cabinet;
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
     * @return array{
     *     dateFrom: string,
     *     dateTo: string,
     *     workStartHour: int,
     *     workEndHour: int,
     *     rows: list<array{
     *         cabinetId: string,
     *         cabinetName: string,
     *         clinicId: ?string,
     *         appointmentCount: int,
     *         finishedCount: int,
     *         occupiedMinutes: int,
     *         availableMinutes: int,
     *         utilizationPercent: float
     *     }>
     * }
     */
    public function getCabinetUtilization(
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int $workStartHour = 8,
        int $workEndHour = 21,
        ?string $clinicId = null,
        int $limit = 20
    ): array {
        $period = $this->normalizePeriod($dateFrom, $dateTo);
        [$workStartHour, $workEndHour] = $this->normalizeWorkHours($workStartHour, $workEndHour);
        $limit = max(1, min(50, $limit));

        $periodStart = $this->timestampOrNull($period['from']) ?? 0;
        $periodEnd = $this->timestampOrNull($period['to']) ?? $periodStart;
        $periodDays = $this->countPeriodDays($period['from'], $period['to']);
        $availableMinutes = $periodDays * ($workEndHour - $workStartHour) * 60;

        $cabinetWhere = [
            'deleted' => false,
            'isActive' => true,
        ];

        if ($clinicId !== null && trim($clinicId) !== '') {
            $cabinetWhere['clinicId'] = trim($clinicId);
        }

        /** @var iterable<Cabinet> $cabinets */
        $cabinets = $this->entityManager
            ->getRDBRepository(Cabinet::ENTITY_TYPE)
            ->where($cabinetWhere)
            ->order('order', 'ASC')
            ->find();

        $rows = [];

        foreach ($cabinets as $cabinet) {
            $cabinetId = (string) $cabinet->getId();

            if ($cabinetId === '') {
                continue;
            }

            $rows[$cabinetId] = [
                'cabinetId' => $cabinetId,
                'cabinetName' => (string) ($cabinet->get('name') ?: $cabinetId),
                'clinicId' => $cabinet->get('clinicId'),
                'appointmentCount' => 0,
                'finishedCount' => 0,
                'occupiedMinutes' => 0,
                'availableMinutes' => $availableMinutes,
                'utilizationPercent' => 0.0,
            ];
        }

        if ($rows !== []) {
            $appointmentWhere = [
                'deleted' => false,
                'status' => array_merge(Appointment::BLOCKING_STATUSES, [Appointment::STATUS_FINISHED]),
                'dateStart<' => $period['to'],
                'dateEnd>' => $period['from'],
            ];

            if ($clinicId !== null && trim($clinicId) !== '') {
                $appointmentWhere['clinicId'] = trim($clinicId);
            }

            /** @var iterable<Appointment> $appointments */
            $appointments = $this->entityManager
                ->getRDBRepository(Appointment::ENTITY_TYPE)
                ->where($appointmentWhere)
                ->find();

            foreach ($appointments as $appointment) {
                $cabinetId = (string) ($appointment->get('cabinetId') ?? '');

                if ($cabinetId === '' || !isset($rows[$cabinetId])) {
                    continue;
                }

                $occupiedMinutes = $this->appointmentOverlapMinutes(
                    $appointment,
                    $periodStart,
                    $periodEnd,
                    $workStartHour,
                    $workEndHour
                );

                if ($occupiedMinutes <= 0) {
                    continue;
                }

                $rows[$cabinetId]['appointmentCount']++;
                $rows[$cabinetId]['occupiedMinutes'] += $occupiedMinutes;

                if ($appointment->getStatus() === Appointment::STATUS_FINISHED) {
                    $rows[$cabinetId]['finishedCount']++;
                }
            }
        }

        foreach ($rows as &$row) {
            $row['utilizationPercent'] = $row['availableMinutes'] > 0
                ? round($row['occupiedMinutes'] / $row['availableMinutes'] * 100, 1)
                : 0.0;
        }
        unset($row);

        usort($rows, static function (array $a, array $b): int {
            return [$b['utilizationPercent'], $b['occupiedMinutes'], $a['cabinetName']]
                <=> [$a['utilizationPercent'], $a['occupiedMinutes'], $b['cabinetName']];
        });

        return [
            'dateFrom' => $period['from'],
            'dateTo' => $period['to'],
            'workStartHour' => $workStartHour,
            'workEndHour' => $workEndHour,
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

        $from = $this->normalizeDateTime($dateFrom, $defaultFrom, false);
        $to = $this->normalizeDateTime($dateTo, $defaultTo, true);

        $fromTs = $this->timestampOrNull($from);
        $toTs = $this->timestampOrNull($to);

        if ($fromTs !== null && $toTs !== null && $toTs <= $fromTs) {
            $to = (new DateTimeImmutable($from))->modify('+1 day')->format('Y-m-d H:i:s');
        }

        return [
            'from' => $from,
            'to' => $to,
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

    /**
     * @return array{int, int}
     */
    private function normalizeWorkHours(int $workStartHour, int $workEndHour): array
    {
        $workStartHour = max(0, min(23, $workStartHour));
        $workEndHour = max(1, min(24, $workEndHour));

        if ($workEndHour <= $workStartHour) {
            return [8, 21];
        }

        return [$workStartHour, $workEndHour];
    }

    private function countPeriodDays(string $from, string $to): int
    {
        try {
            $fromDate = (new DateTimeImmutable($from))->setTime(0, 0, 0);
            $toDate = (new DateTimeImmutable($to))->setTime(0, 0, 0);
        } catch (\Exception) {
            return 1;
        }

        $days = (int) $fromDate->diff($toDate)->days;

        return max(1, $days);
    }

    private function appointmentOverlapMinutes(
        Appointment $appointment,
        int $periodStart,
        int $periodEnd,
        int $workStartHour,
        int $workEndHour
    ): int {
        $start = $this->timestampOrNull((string) $appointment->getDateStart());
        $end = $this->timestampOrNull((string) $appointment->getDateEnd());

        if ($start === null) {
            return 0;
        }

        if ($end === null || $end <= $start) {
            $durationSeconds = (int) ($appointment->get('duration') ?? 0);

            if ($durationSeconds <= 0) {
                return 0;
            }

            $end = $start + $durationSeconds;
        }

        return $this->businessOverlapMinutes($start, $end, $periodStart, $periodEnd, $workStartHour, $workEndHour);
    }

    private function businessOverlapMinutes(
        int $start,
        int $end,
        int $periodStart,
        int $periodEnd,
        int $workStartHour,
        int $workEndHour
    ): int {
        $start = max($start, $periodStart);
        $end = min($end, $periodEnd);

        if ($end <= $start) {
            return 0;
        }

        $minutes = 0;
        $day = (new DateTimeImmutable('@' . $start))->setTime(0, 0, 0);
        $lastDay = (new DateTimeImmutable('@' . $end))->setTime(0, 0, 0);

        while ($day <= $lastDay) {
            $windowStart = $day->setTime($workStartHour, 0, 0)->getTimestamp();
            $windowEnd = $day->setTime($workEndHour, 0, 0)->getTimestamp();
            $minutes += $this->overlapMinutes($start, $end, $windowStart, $windowEnd);
            $day = $day->modify('+1 day');
        }

        return $minutes;
    }

    private function overlapMinutes(int $start, int $end, int $periodStart, int $periodEnd): int
    {
        $start = max($start, $periodStart);
        $end = min($end, $periodEnd);

        if ($end <= $start) {
            return 0;
        }

        return (int) ceil(($end - $start) / 60);
    }

    private function timestampOrNull(string $value): ?int
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value))->getTimestamp();
        } catch (\Exception) {
            return null;
        }
    }
}
