<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\Appointment;
use Espo\Modules\EspoDental\Entities\Cabinet;
use Espo\Modules\EspoDental\Entities\Invoice;
use Espo\Modules\EspoDental\Entities\LowStockAlert;
use Espo\Modules\EspoDental\Entities\Material;
use Espo\Modules\EspoDental\Entities\Patient;
use Espo\Modules\EspoDental\Entities\Payment;
use Espo\Modules\EspoDental\Entities\PreliminaryPatient;
use Espo\Modules\EspoDental\Entities\SalaryEntry;
use Espo\Modules\EspoDental\Entities\StockMovement;
use Espo\Modules\EspoDental\Entities\Visit;
use Espo\Modules\EspoDental\Entities\VisitMaterialLine;
use Espo\Modules\EspoDental\Entities\VisitServiceLine;

class ReportService
{
    /** @var array<string, string> */
    private const EXPORT_SOURCE_ALIASES = [
        'managementSnapshot' => 'finance',
        'management_snapshot' => 'finance',
        'doctorProductivity' => 'doctor_utilization',
        'cabinetUtilization' => 'cabinet_utilization',
        'noShowCancellations' => 'appointments',
        'inventoryStatus' => 'inventory',
    ];

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

    private function sumPaymentsBetween(string $from, string $to, ?string $clinicId = null): float
    {
        $where = [
            'paidAt>=' => $from,
            'paidAt<' => $to,
            'direction' => Payment::DIRECTION_IN,
            'status' => Payment::STATUS_COMPLETED,
            'deleted' => false,
        ];

        if ($clinicId !== null && trim($clinicId) !== '') {
            $where['clinicId'] = trim($clinicId);
        }

        $qb = $this->entityManager
            ->getQueryBuilder()
            ->select(['SUM:amount'])
            ->from(Payment::ENTITY_TYPE)
            ->where($where)
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
     * @return array{
     *     dateFrom: string,
     *     dateTo: string,
     *     finance: array{
     *         revenue: float,
     *         openInvoiceCount: int,
     *         overdueInvoiceCount: int,
     *         openInvoiceBalance: float,
     *         materialCost: float,
     *         payrollAccrued: float,
     *         grossAfterKnownCosts: float
     *     },
     *     appointmentQuality: array{
     *         appointmentCount: int,
     *         noShowCount: int,
     *         cancellationCount: int,
     *         issueCount: int,
     *         noShowRate: float,
     *         cancellationRate: float,
     *         issueRate: float
     *     },
     *     stock: array<string, mixed>,
     *     payroll: array<string, mixed>,
     *     doctorRows: list<array<string, mixed>>,
     *     cabinetRows: list<array<string, mixed>>,
     *     stockRows: list<array<string, mixed>>
     * }
     */
    public function getManagementSnapshot(
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $clinicId = null,
        int $limit = 5
    ): array {
        $period = $this->normalizePeriod($dateFrom, $dateTo);
        $clinicId = $this->normalizeOptionalId($clinicId);
        $limit = max(1, min(12, $limit));

        $revenue = $this->sumPaymentsBetween($period['from'], $period['to'], $clinicId);
        $invoiceRisk = $this->getInvoiceRisk($clinicId);
        $materialCost = $this->getMaterialCost($period, $clinicId);
        $payroll = $this->getPayrollSnapshot($period, $clinicId, $limit);
        $appointmentQuality = $this->getNoShowCancellations($period['from'], $period['to'], $clinicId, $limit);
        $inventory = $this->getInventoryStatus($period['from'], $period['to'], $clinicId, $limit);
        $doctorProductivity = $this->getDoctorProductivity($period['from'], $period['to'], $limit);
        $cabinetUtilization = $this->getCabinetUtilization(
            $period['from'],
            $period['to'],
            8,
            21,
            $clinicId,
            $limit
        );

        $grossAfterKnownCosts = round($revenue - $materialCost - (float) $payroll['totalAmount'], 2);

        return [
            'dateFrom' => $period['from'],
            'dateTo' => $period['to'],
            'finance' => [
                'revenue' => round($revenue, 2),
                'openInvoiceCount' => $invoiceRisk['openInvoiceCount'],
                'overdueInvoiceCount' => $invoiceRisk['overdueInvoiceCount'],
                'openInvoiceBalance' => $invoiceRisk['openInvoiceBalance'],
                'materialCost' => $materialCost,
                'payrollAccrued' => (float) $payroll['totalAmount'],
                'grossAfterKnownCosts' => $grossAfterKnownCosts,
            ],
            'appointmentQuality' => $appointmentQuality['summary'],
            'stock' => $inventory['summary'],
            'payroll' => $payroll,
            'doctorRows' => $doctorProductivity['rows'],
            'cabinetRows' => $cabinetUtilization['rows'],
            'stockRows' => $inventory['rows'],
        ];
    }

    /**
     * @return array{
     *     source: string,
     *     format: string,
     *     filename: string,
     *     mimeType: string,
     *     dateFrom: string,
     *     dateTo: string,
     *     columns: list<array{key: string, label: string}>,
     *     rows: list<array<string, mixed>>,
     *     content: string
     * }
     */
    public function exportReport(
        string $source,
        string $format = 'csv',
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $clinicId = null,
        int $limit = 100
    ): array {
        $source = $this->normalizeExportSource($source);
        $format = $this->normalizeExportFormat($format);
        $period = $this->normalizePeriod($dateFrom, $dateTo);
        $clinicId = $this->normalizeOptionalId($clinicId);
        $limit = max(1, min(500, $limit));

        [$columns, $rows] = match ($source) {
            'payments' => $this->buildPaymentsExport($period, $clinicId, $limit),
            'finance' => $this->buildFinanceExport($period, $clinicId, $limit),
            'service_profitability' => $this->buildServiceProfitabilityExport($period, $clinicId, $limit),
            'material_finance' => $this->buildMaterialFinanceExport($period, $clinicId, $limit),
            'doctor_utilization' => $this->buildDoctorUtilizationExport($period, $limit),
            'cabinet_utilization' => $this->buildCabinetUtilizationExport($period, $clinicId, $limit),
            'patient_funnel' => $this->buildPatientFunnelExport($clinicId),
            'appointments' => $this->buildAppointmentsExport($period, $clinicId, $limit),
            'inventory' => $this->buildInventoryExport($period, $clinicId, $limit),
            'payroll' => $this->buildPayrollExport($period, $clinicId, $limit),
        };

        $filename = sprintf(
            'espo-dental-%s-%s-%s.%s',
            str_replace('_', '-', $source),
            substr($period['from'], 0, 10),
            substr($period['to'], 0, 10),
            $format
        );
        $content = $format === 'json'
            ? $this->renderExportJson($source, $period, $columns, $rows)
            : $this->renderExportCsv($columns, $rows);

        return [
            'source' => $source,
            'format' => $format,
            'filename' => $filename,
            'mimeType' => $format === 'json' ? 'application/json; charset=utf-8' : 'text/csv; charset=utf-8',
            'dateFrom' => $period['from'],
            'dateTo' => $period['to'],
            'columns' => $columns,
            'rows' => $rows,
            'content' => $content,
        ];
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
     * @return array{
     *     dateFrom: string,
     *     dateTo: string,
     *     summary: array{
     *         appointmentCount: int,
     *         noShowCount: int,
     *         cancellationCount: int,
     *         issueCount: int,
     *         noShowRate: float,
     *         cancellationRate: float,
     *         issueRate: float
     *     },
     *     rows: list<array{
     *         doctorId: ?string,
     *         doctorName: string,
     *         appointmentCount: int,
     *         noShowCount: int,
     *         cancellationCount: int,
     *         issueCount: int,
     *         noShowRate: float,
     *         cancellationRate: float,
     *         issueRate: float
     *     }>
     * }
     */
    public function getNoShowCancellations(
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $clinicId = null,
        int $limit = 10
    ): array {
        $period = $this->normalizePeriod($dateFrom, $dateTo);
        $limit = max(1, min(50, $limit));
        $clinicId = $this->normalizeOptionalId($clinicId);

        $appointmentWhere = [
            'deleted' => false,
            'dateStart>=' => $period['from'],
            'dateStart<' => $period['to'],
        ];

        if ($clinicId !== null) {
            $appointmentWhere['clinicId'] = $clinicId;
        }

        /** @var iterable<Appointment> $appointments */
        $appointments = $this->entityManager
            ->getRDBRepository(Appointment::ENTITY_TYPE)
            ->where($appointmentWhere)
            ->find();

        $summary = [
            'appointmentCount' => 0,
            'noShowCount' => 0,
            'cancellationCount' => 0,
            'issueCount' => 0,
            'noShowRate' => 0.0,
            'cancellationRate' => 0.0,
            'issueRate' => 0.0,
        ];
        $rows = [];

        foreach ($appointments as $appointment) {
            $doctorId = $this->normalizeOptionalId($appointment->get('doctorId'));
            $rowKey = $doctorId ?? '__unassigned__';

            if (!isset($rows[$rowKey])) {
                $rows[$rowKey] = [
                    'doctorId' => $doctorId,
                    'doctorName' => (string) ($appointment->get('doctorName') ?: ($doctorId ?? 'Unassigned')),
                    'appointmentCount' => 0,
                    'noShowCount' => 0,
                    'cancellationCount' => 0,
                    'issueCount' => 0,
                    'noShowRate' => 0.0,
                    'cancellationRate' => 0.0,
                    'issueRate' => 0.0,
                ];
            }

            $status = (string) $appointment->getStatus();

            $summary['appointmentCount']++;
            $rows[$rowKey]['appointmentCount']++;

            if ($status === Appointment::STATUS_NO_SHOW) {
                $summary['noShowCount']++;
                $summary['issueCount']++;
                $rows[$rowKey]['noShowCount']++;
                $rows[$rowKey]['issueCount']++;
            }

            if ($status === Appointment::STATUS_CANCELLED) {
                $summary['cancellationCount']++;
                $summary['issueCount']++;
                $rows[$rowKey]['cancellationCount']++;
                $rows[$rowKey]['issueCount']++;
            }
        }

        $summary = $this->withAppointmentRates($summary);

        foreach ($rows as &$row) {
            $row = $this->withAppointmentRates($row);
        }
        unset($row);

        usort($rows, static function (array $a, array $b): int {
            return [$b['issueRate'], $b['issueCount'], $a['doctorName']]
                <=> [$a['issueRate'], $a['issueCount'], $b['doctorName']];
        });

        return [
            'dateFrom' => $period['from'],
            'dateTo' => $period['to'],
            'summary' => $summary,
            'rows' => array_slice(array_values($rows), 0, $limit),
        ];
    }

    /**
     * @return array{
     *     dateFrom: string,
     *     dateTo: string,
     *     summary: array{
     *         materialCount: int,
     *         lowStockCount: int,
     *         criticalStockCount: int,
     *         outStockCount: int,
     *         inventoryValue: float,
     *         inboundQuantity: float,
     *         outboundQuantity: float,
     *         netQuantity: float
     *     },
     *     rows: list<array{
     *         materialId: string,
     *         materialName: string,
     *         categoryName: string,
     *         unit: string,
     *         stockLevel: string,
     *         currentStock: float,
     *         minStock: float,
     *         criticalStock: float,
     *         inventoryValue: float,
     *         inboundQuantity: float,
     *         outboundQuantity: float,
     *         netQuantity: float
     *     }>
     * }
     */
    public function getInventoryStatus(
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $clinicId = null,
        int $limit = 15
    ): array {
        $period = $this->normalizePeriod($dateFrom, $dateTo);
        $clinicId = $this->normalizeOptionalId($clinicId);
        $limit = max(1, min(50, $limit));

        /** @var iterable<Material> $materials */
        $materials = $this->entityManager
            ->getRDBRepository(Material::ENTITY_TYPE)
            ->where([
                'deleted' => false,
                'isActive' => true,
            ])
            ->order('name', 'ASC')
            ->find();

        $summary = [
            'materialCount' => 0,
            'lowStockCount' => 0,
            'criticalStockCount' => 0,
            'outStockCount' => 0,
            'inventoryValue' => 0.0,
            'inboundQuantity' => 0.0,
            'outboundQuantity' => 0.0,
            'netQuantity' => 0.0,
        ];
        $rows = [];

        foreach ($materials as $material) {
            $materialId = (string) $material->getId();

            if ($materialId === '') {
                continue;
            }

            $level = (string) ($material->get('stockLevel') ?: $material->computeLevel());
            $currentStock = round((float) $material->get('currentStock'), 3);
            $inventoryValue = round($currentStock * (float) ($material->get('price') ?? 0.0), 2);

            $rows[$materialId] = [
                'materialId' => $materialId,
                'materialName' => (string) ($material->get('name') ?: $materialId),
                'categoryName' => (string) ($material->get('categoryName') ?: ''),
                'unit' => (string) ($material->get('unit') ?: ''),
                'stockLevel' => $level,
                'currentStock' => $currentStock,
                'minStock' => round((float) ($material->get('minStock') ?? 0.0), 3),
                'criticalStock' => round((float) ($material->get('criticalStock') ?? 0.0), 3),
                'inventoryValue' => $inventoryValue,
                'inboundQuantity' => 0.0,
                'outboundQuantity' => 0.0,
                'netQuantity' => 0.0,
            ];

            $summary['materialCount']++;
            $summary['inventoryValue'] += $inventoryValue;

            if ($level === Material::LEVEL_LOW) {
                $summary['lowStockCount']++;
            }

            if ($level === Material::LEVEL_CRITICAL) {
                $summary['criticalStockCount']++;
            }

            if ($level === Material::LEVEL_OUT) {
                $summary['outStockCount']++;
            }
        }

        if ($rows !== []) {
            $movementWhere = [
                'deleted' => false,
                'performedAt>=' => $period['from'],
                'performedAt<' => $period['to'],
            ];

            if ($clinicId !== null) {
                $movementWhere['clinicId'] = $clinicId;
            }

            /** @var iterable<StockMovement> $movements */
            $movements = $this->entityManager
                ->getRDBRepository(StockMovement::ENTITY_TYPE)
                ->where($movementWhere)
                ->find();

            foreach ($movements as $movement) {
                $materialId = (string) ($movement->get('materialId') ?? '');

                if ($materialId === '' || !isset($rows[$materialId])) {
                    continue;
                }

                $quantity = round(abs($movement->getSignedQuantity()), 3);
                $isOutbound = $movement->getSignedQuantity() < 0;

                if ($isOutbound) {
                    $rows[$materialId]['outboundQuantity'] += $quantity;
                    $summary['outboundQuantity'] += $quantity;
                } else {
                    $rows[$materialId]['inboundQuantity'] += $quantity;
                    $summary['inboundQuantity'] += $quantity;
                }

                $rows[$materialId]['netQuantity'] += $movement->getSignedQuantity();
                $summary['netQuantity'] += $movement->getSignedQuantity();
            }
        }

        foreach ($rows as &$row) {
            $row['inboundQuantity'] = round($row['inboundQuantity'], 3);
            $row['outboundQuantity'] = round($row['outboundQuantity'], 3);
            $row['netQuantity'] = round($row['netQuantity'], 3);
        }
        unset($row);

        $summary['inventoryValue'] = round($summary['inventoryValue'], 2);
        $summary['inboundQuantity'] = round($summary['inboundQuantity'], 3);
        $summary['outboundQuantity'] = round($summary['outboundQuantity'], 3);
        $summary['netQuantity'] = round($summary['netQuantity'], 3);

        usort($rows, function (array $a, array $b): int {
            return [
                $this->stockLevelRank($b['stockLevel']),
                $b['outboundQuantity'],
                $a['materialName'],
            ] <=> [
                $this->stockLevelRank($a['stockLevel']),
                $a['outboundQuantity'],
                $b['materialName'],
            ];
        });

        return [
            'dateFrom' => $period['from'],
            'dateTo' => $period['to'],
            'summary' => $summary,
            'rows' => array_slice(array_values($rows), 0, $limit),
        ];
    }

    private function normalizeExportSource(string $source): string
    {
        $source = trim($source) ?: 'finance';
        $source = self::EXPORT_SOURCE_ALIASES[$source] ?? $source;
        $supported = [
            'payments',
            'finance',
            'service_profitability',
            'material_finance',
            'doctor_utilization',
            'cabinet_utilization',
            'patient_funnel',
            'appointments',
            'inventory',
            'payroll',
        ];

        if (!in_array($source, $supported, true)) {
            throw new BadRequest('Unsupported report export source');
        }

        return $source;
    }

    private function normalizeExportFormat(string $format): string
    {
        $format = strtolower(trim($format)) ?: 'csv';

        if (!in_array($format, ['csv', 'json'], true)) {
            throw new BadRequest('Unsupported report export format');
        }

        return $format;
    }

    /**
     * @param array{from: string, to: string} $period
     * @return array{0: list<array{key: string, label: string}>, 1: list<array<string, mixed>>}
     */
    private function buildPaymentsExport(array $period, ?string $clinicId, int $limit): array
    {
        $where = [
            'deleted' => false,
            'paidAt>=' => $period['from'],
            'paidAt<' => $period['to'],
        ];

        if ($clinicId !== null) {
            $where['clinicId'] = $clinicId;
        }

        /** @var iterable<Payment> $payments */
        $payments = $this->entityManager
            ->getRDBRepository(Payment::ENTITY_TYPE)
            ->where($where)
            ->order('paidAt', 'DESC')
            ->find();

        $rows = [];
        foreach ($payments as $payment) {
            $rows[] = [
                'paidAt' => (string) ($payment->get('paidAt') ?? ''),
                'method' => (string) ($payment->get('method') ?? ''),
                'direction' => (string) ($payment->get('direction') ?? ''),
                'status' => (string) ($payment->get('status') ?? ''),
                'amount' => round($payment->getAmount(), 2),
                'currency' => (string) ($payment->get('currency') ?? ''),
                'invoiceId' => (string) ($payment->get('invoiceId') ?? ''),
                'patientId' => (string) ($payment->get('patientId') ?? ''),
            ];

            if (count($rows) >= $limit) {
                break;
            }
        }

        return [
            $this->columns([
                'paidAt' => 'Дата оплаты',
                'method' => 'Метод',
                'direction' => 'Направление',
                'status' => 'Статус',
                'amount' => 'Сумма',
                'currency' => 'Валюта',
                'invoiceId' => 'Счет',
                'patientId' => 'Пациент',
            ]),
            $rows,
        ];
    }

    /**
     * @param array{from: string, to: string} $period
     * @return array{0: list<array{key: string, label: string}>, 1: list<array<string, mixed>>}
     */
    private function buildFinanceExport(array $period, ?string $clinicId, int $limit): array
    {
        $snapshot = $this->getManagementSnapshot($period['from'], $period['to'], $clinicId, min(12, $limit));
        $rows = [];

        foreach ($snapshot['finance'] as $key => $value) {
            $rows[] = [
                'section' => 'finance',
                'item' => $key,
                'metric' => $key,
                'value' => $value,
                'extra' => '',
            ];
        }

        foreach ($snapshot['appointmentQuality'] as $key => $value) {
            $rows[] = [
                'section' => 'appointment_quality',
                'item' => $key,
                'metric' => $key,
                'value' => $value,
                'extra' => '',
            ];
        }

        foreach ($snapshot['stock'] as $key => $value) {
            $rows[] = [
                'section' => 'stock',
                'item' => $key,
                'metric' => $key,
                'value' => $value,
                'extra' => '',
            ];
        }

        foreach ($snapshot['doctorRows'] as $row) {
            $rows[] = [
                'section' => 'doctor',
                'item' => $row['doctorName'] ?? $row['doctorId'] ?? '',
                'metric' => 'grossAmount',
                'value' => $row['grossAmount'] ?? 0,
                'extra' => 'visits=' . (string) ($row['visitCount'] ?? 0),
            ];
        }

        foreach ($snapshot['cabinetRows'] as $row) {
            $rows[] = [
                'section' => 'cabinet',
                'item' => $row['cabinetName'] ?? $row['cabinetId'] ?? '',
                'metric' => 'utilizationPercent',
                'value' => $row['utilizationPercent'] ?? 0,
                'extra' => 'occupiedMinutes=' . (string) ($row['occupiedMinutes'] ?? 0),
            ];
        }

        foreach (($snapshot['payroll']['rows'] ?? []) as $row) {
            $rows[] = [
                'section' => 'payroll',
                'item' => $row['userName'] ?? $row['userId'] ?? '',
                'metric' => (string) ($row['status'] ?? ''),
                'value' => $row['totalAmount'] ?? 0,
                'extra' => trim((string) ($row['periodFrom'] ?? '') . ' ' . (string) ($row['periodTo'] ?? '')),
            ];
        }

        return [
            $this->columns([
                'section' => 'Раздел',
                'item' => 'Объект',
                'metric' => 'Показатель',
                'value' => 'Значение',
                'extra' => 'Детали',
            ]),
            $rows,
        ];
    }

    /**
     * @param array{from: string, to: string} $period
     * @return array{0: list<array{key: string, label: string}>, 1: list<array<string, mixed>>}
     */
    private function buildServiceProfitabilityExport(array $period, ?string $clinicId, int $limit): array
    {
        $visitIds = $this->getFinishedVisitIds($period, $clinicId);
        $rows = [];

        if ($visitIds !== []) {
            $materialCostByServiceLine = $this->getMaterialCostByServiceLine($visitIds);
            /** @var iterable<VisitServiceLine> $lines */
            $lines = $this->entityManager
                ->getRDBRepository(VisitServiceLine::ENTITY_TYPE)
                ->where(['deleted' => false, 'visitId' => $visitIds])
                ->find();

            foreach ($lines as $line) {
                $serviceId = (string) ($line->get('serviceId') ?? '');
                $key = $serviceId !== '' ? $serviceId : (string) $line->getId();

                if (!isset($rows[$key])) {
                    $rows[$key] = [
                        'serviceId' => $serviceId,
                        'serviceName' => (string) ($line->get('serviceName') ?: $serviceId ?: $line->getId()),
                        'quantity' => 0,
                        'revenue' => 0.0,
                        'materialCost' => 0.0,
                        'profit' => 0.0,
                    ];
                }

                $rows[$key]['quantity'] += $line->getQuantity();
                $rows[$key]['revenue'] += $line->getAmount();
                $rows[$key]['materialCost'] += $materialCostByServiceLine[(string) $line->getId()] ?? 0.0;
            }
        }

        foreach ($rows as &$row) {
            $row['revenue'] = round($row['revenue'], 2);
            $row['materialCost'] = round($row['materialCost'], 2);
            $row['profit'] = round($row['revenue'] - $row['materialCost'], 2);
        }
        unset($row);

        usort($rows, static fn (array $a, array $b): int => [$b['profit'], $b['revenue']]
            <=> [$a['profit'], $a['revenue']]);

        return [
            $this->columns([
                'serviceName' => 'Услуга',
                'quantity' => 'Количество',
                'revenue' => 'Выручка',
                'materialCost' => 'Материалы',
                'profit' => 'Маржа',
                'serviceId' => 'ID услуги',
            ]),
            array_slice(array_values($rows), 0, $limit),
        ];
    }

    /**
     * @param array{from: string, to: string} $period
     * @return array{0: list<array{key: string, label: string}>, 1: list<array<string, mixed>>}
     */
    private function buildMaterialFinanceExport(array $period, ?string $clinicId, int $limit): array
    {
        $where = [
            'deleted' => false,
            'performedAt>=' => $period['from'],
            'performedAt<' => $period['to'],
        ];

        if ($clinicId !== null) {
            $where['clinicId'] = $clinicId;
        }

        /** @var iterable<StockMovement> $movements */
        $movements = $this->entityManager
            ->getRDBRepository(StockMovement::ENTITY_TYPE)
            ->where($where)
            ->order('performedAt', 'DESC')
            ->find();

        $rows = [];
        foreach ($movements as $movement) {
            $rows[] = [
                'performedAt' => (string) ($movement->get('performedAt') ?? ''),
                'materialName' => (string) ($movement->get('materialName') ?: $movement->get('materialId') ?: ''),
                'type' => (string) ($movement->get('type') ?? ''),
                'quantity' => round((float) ($movement->get('quantity') ?? 0), 3),
                'signedQuantity' => round($movement->getSignedQuantity(), 3),
                'unitPrice' => round((float) ($movement->get('unitPrice') ?? 0), 2),
                'totalCost' => round((float) ($movement->get('totalCost') ?? 0), 2),
                'sourceWarehouseName' => (string) ($movement->get('sourceWarehouseName') ?? ''),
                'targetWarehouseName' => (string) ($movement->get('targetWarehouseName') ?? ''),
            ];

            if (count($rows) >= $limit) {
                break;
            }
        }

        return [
            $this->columns([
                'performedAt' => 'Дата',
                'materialName' => 'Материал',
                'type' => 'Тип движения',
                'quantity' => 'Количество',
                'signedQuantity' => 'Знак. количество',
                'unitPrice' => 'Цена',
                'totalCost' => 'Стоимость',
                'sourceWarehouseName' => 'Откуда',
                'targetWarehouseName' => 'Куда',
            ]),
            $rows,
        ];
    }

    /**
     * @param array{from: string, to: string} $period
     * @return array{0: list<array{key: string, label: string}>, 1: list<array<string, mixed>>}
     */
    private function buildDoctorUtilizationExport(array $period, int $limit): array
    {
        return [
            $this->columns([
                'doctorName' => 'Врач',
                'visitCount' => 'Приемы',
                'serviceLineCount' => 'Услуги',
                'grossAmount' => 'Выручка',
                'averageVisitAmount' => 'Средний чек',
                'doctorId' => 'ID врача',
            ]),
            $this->getDoctorProductivity($period['from'], $period['to'], $limit)['rows'],
        ];
    }

    /**
     * @param array{from: string, to: string} $period
     * @return array{0: list<array{key: string, label: string}>, 1: list<array<string, mixed>>}
     */
    private function buildCabinetUtilizationExport(array $period, ?string $clinicId, int $limit): array
    {
        return [
            $this->columns([
                'cabinetName' => 'Кабинет',
                'appointmentCount' => 'Записи',
                'finishedCount' => 'Завершено',
                'occupiedMinutes' => 'Занято, мин',
                'availableMinutes' => 'Доступно, мин',
                'utilizationPercent' => 'Загрузка, %',
                'cabinetId' => 'ID кабинета',
            ]),
            $this->getCabinetUtilization($period['from'], $period['to'], 8, 21, $clinicId, $limit)['rows'],
        ];
    }

    /**
     * @return array{0: list<array{key: string, label: string}>, 1: list<array<string, mixed>>}
     */
    private function buildPatientFunnelExport(?string $clinicId): array
    {
        $preliminaryWhere = ['deleted' => false];
        $patientWhere = ['deleted' => false];

        if ($clinicId !== null) {
            $preliminaryWhere['clinicId'] = $clinicId;
            $patientWhere['clinicId'] = $clinicId;
        }

        /** @var iterable<PreliminaryPatient> $preliminaryPatients */
        $preliminaryPatients = $this->entityManager
            ->getRDBRepository(PreliminaryPatient::ENTITY_TYPE)
            ->where($preliminaryWhere)
            ->find();

        $leadCounts = [
            PreliminaryPatient::STATUS_ENTERED => 0,
            PreliminaryPatient::STATUS_BOOKED => 0,
            PreliminaryPatient::STATUS_PROCESSED => 0,
            PreliminaryPatient::STATUS_NO_SHOW => 0,
        ];
        $convertedCount = 0;

        foreach ($preliminaryPatients as $patient) {
            $status = (string) ($patient->getStatus() ?: PreliminaryPatient::STATUS_ENTERED);
            $leadCounts[$status] = ($leadCounts[$status] ?? 0) + 1;

            if ($patient->isConverted()) {
                $convertedCount++;
            }
        }

        $totalLeads = array_sum($leadCounts);
        $rows = [];

        foreach ($leadCounts as $stage => $count) {
            $rows[] = [
                'stage' => $stage,
                'patientCount' => $count,
                'conversionPercent' => $totalLeads > 0 ? round($count / $totalLeads * 100, 1) : 0.0,
            ];
        }

        $rows[] = [
            'stage' => 'converted',
            'patientCount' => $convertedCount,
            'conversionPercent' => $totalLeads > 0 ? round($convertedCount / $totalLeads * 100, 1) : 0.0,
        ];

        /** @var iterable<Patient> $patients */
        $patients = $this->entityManager
            ->getRDBRepository(Patient::ENTITY_TYPE)
            ->where($patientWhere)
            ->find();

        $activePatients = 0;
        foreach ($patients as $patient) {
            if ($patient->getStatus() === Patient::STATUS_ACTIVE) {
                $activePatients++;
            }
        }

        $rows[] = [
            'stage' => 'active_patient',
            'patientCount' => $activePatients,
            'conversionPercent' => 0.0,
        ];

        return [
            $this->columns([
                'stage' => 'Этап',
                'patientCount' => 'Пациенты',
                'conversionPercent' => 'Доля, %',
            ]),
            $rows,
        ];
    }

    /**
     * @param array{from: string, to: string} $period
     * @return array{0: list<array{key: string, label: string}>, 1: list<array<string, mixed>>}
     */
    private function buildAppointmentsExport(array $period, ?string $clinicId, int $limit): array
    {
        return [
            $this->columns([
                'doctorName' => 'Врач',
                'appointmentCount' => 'Записи',
                'noShowCount' => 'Неявки',
                'cancellationCount' => 'Отмены',
                'issueCount' => 'Проблемы',
                'noShowRate' => 'Неявки, %',
                'cancellationRate' => 'Отмены, %',
                'issueRate' => 'Проблемы, %',
                'doctorId' => 'ID врача',
            ]),
            $this->getNoShowCancellations($period['from'], $period['to'], $clinicId, $limit)['rows'],
        ];
    }

    /**
     * @param array{from: string, to: string} $period
     * @return array{0: list<array{key: string, label: string}>, 1: list<array<string, mixed>>}
     */
    private function buildInventoryExport(array $period, ?string $clinicId, int $limit): array
    {
        return [
            $this->columns([
                'materialName' => 'Материал',
                'categoryName' => 'Категория',
                'unit' => 'Ед.',
                'stockLevel' => 'Уровень',
                'currentStock' => 'Остаток',
                'minStock' => 'Мин.',
                'criticalStock' => 'Крит.',
                'inventoryValue' => 'Стоимость',
                'inboundQuantity' => 'Приход',
                'outboundQuantity' => 'Расход',
                'netQuantity' => 'Нетто',
                'materialId' => 'ID материала',
            ]),
            $this->getInventoryStatus($period['from'], $period['to'], $clinicId, $limit)['rows'],
        ];
    }

    /**
     * @param array{from: string, to: string} $period
     * @return array{0: list<array{key: string, label: string}>, 1: list<array<string, mixed>>}
     */
    private function buildPayrollExport(array $period, ?string $clinicId, int $limit): array
    {
        $where = [
            'deleted' => false,
            'periodFrom<' => substr($period['to'], 0, 10),
            'periodTo>=' => substr($period['from'], 0, 10),
        ];

        if ($clinicId !== null) {
            $where['clinicId'] = $clinicId;
        }

        /** @var iterable<SalaryEntry> $entries */
        $entries = $this->entityManager
            ->getRDBRepository(SalaryEntry::ENTITY_TYPE)
            ->where($where)
            ->order('totalAmount', 'DESC')
            ->find();

        $rows = [];
        foreach ($entries as $entry) {
            if ($entry->getStatus() === SalaryEntry::STATUS_CANCELLED) {
                continue;
            }

            $rows[] = [
                'userName' => (string) ($entry->get('userName') ?: $entry->get('userId') ?: ''),
                'status' => $entry->getStatus(),
                'totalAmount' => round($entry->getTotalAmount(), 2),
                'baseAmount' => round($entry->getBaseAmount(), 2),
                'revenueAmount' => round($entry->getRevenueAmount(), 2),
                'assistantAmount' => round($entry->getAssistantAmount(), 2),
                'bonusAmount' => round($entry->getBonusAmount(), 2),
                'deductionAmount' => round($entry->getDeductionAmount(), 2),
                'periodFrom' => (string) ($entry->get('periodFrom') ?? ''),
                'periodTo' => (string) ($entry->get('periodTo') ?? ''),
                'sourceBreakdown' => $entry->get('sourceBreakdown'),
            ];

            if (count($rows) >= $limit) {
                break;
            }
        }

        return [
            $this->columns([
                'userName' => 'Сотрудник',
                'status' => 'Статус',
                'totalAmount' => 'Итого',
                'baseAmount' => 'База',
                'revenueAmount' => 'Процент врача',
                'assistantAmount' => 'Ассистент',
                'bonusAmount' => 'Бонус',
                'deductionAmount' => 'Удержание',
                'periodFrom' => 'Период с',
                'periodTo' => 'Период по',
                'sourceBreakdown' => 'Источник',
            ]),
            $rows,
        ];
    }

    /**
     * @param array{from: string, to: string} $period
     * @return list<string>
     */
    private function getFinishedVisitIds(array $period, ?string $clinicId): array
    {
        $where = [
            'deleted' => false,
            'status' => Visit::STATUS_FINISHED,
            'startedAt>=' => $period['from'],
            'startedAt<' => $period['to'],
        ];

        if ($clinicId !== null) {
            $where['clinicId'] = $clinicId;
        }

        /** @var iterable<Visit> $visits */
        $visits = $this->entityManager
            ->getRDBRepository(Visit::ENTITY_TYPE)
            ->where($where)
            ->find();

        $ids = [];
        foreach ($visits as $visit) {
            $ids[] = (string) $visit->getId();
        }

        return $ids;
    }

    /**
     * @param list<string> $visitIds
     * @return array<string, float>
     */
    private function getMaterialCostByServiceLine(array $visitIds): array
    {
        /** @var iterable<VisitMaterialLine> $materialLines */
        $materialLines = $this->entityManager
            ->getRDBRepository(VisitMaterialLine::ENTITY_TYPE)
            ->where(['deleted' => false, 'visitId' => $visitIds])
            ->find();

        $costs = [];
        foreach ($materialLines as $line) {
            $serviceLineId = (string) ($line->get('visitServiceLineId') ?? '');

            if ($serviceLineId === '') {
                continue;
            }

            $costs[$serviceLineId] = ($costs[$serviceLineId] ?? 0.0) + (float) ($line->get('totalCost') ?? 0.0);
        }

        return $costs;
    }

    /**
     * @param array<string, string> $columns
     * @return list<array{key: string, label: string}>
     */
    private function columns(array $columns): array
    {
        $rows = [];

        foreach ($columns as $key => $label) {
            $rows[] = ['key' => $key, 'label' => $label];
        }

        return $rows;
    }

    /**
     * @param list<array{key: string, label: string}> $columns
     * @param list<array<string, mixed>> $rows
     */
    private function renderExportCsv(array $columns, array $rows): string
    {
        $lines = [$this->renderCsvLine(array_column($columns, 'label'))];

        foreach ($rows as $row) {
            $values = [];

            foreach ($columns as $column) {
                $values[] = $row[$column['key']] ?? '';
            }

            $lines[] = $this->renderCsvLine($values);
        }

        return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
    }

    /**
     * @param list<mixed> $values
     */
    private function renderCsvLine(array $values): string
    {
        return implode(',', array_map(fn (mixed $value): string => $this->renderCsvValue($value), $values));
    }

    private function renderCsvValue(mixed $value): string
    {
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        } elseif (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $value = str_replace(["\r\n", "\r"], "\n", (string) $value);

        return '"' . str_replace('"', '""', $value) . '"';
    }

    /**
     * @param array{from: string, to: string} $period
     * @param list<array{key: string, label: string}> $columns
     * @param list<array<string, mixed>> $rows
     */
    private function renderExportJson(string $source, array $period, array $columns, array $rows): string
    {
        return (string) json_encode(
            [
                'source' => $source,
                'dateFrom' => $period['from'],
                'dateTo' => $period['to'],
                'columns' => $columns,
                'rows' => $rows,
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );
    }

    /**
     * @param array{from: string, to: string} $period
     */
    private function getMaterialCost(array $period, ?string $clinicId): float
    {
        $where = [
            'deleted' => false,
            'performedAt>=' => $period['from'],
            'performedAt<' => $period['to'],
            'type' => [
                StockMovement::TYPE_CONSUMPTION,
                StockMovement::TYPE_WRITEOFF,
                StockMovement::TYPE_RECEPTION_USAGE,
                StockMovement::TYPE_MANUAL_DECREASE,
            ],
        ];

        if ($clinicId !== null) {
            $where['clinicId'] = $clinicId;
        }

        /** @var iterable<StockMovement> $movements */
        $movements = $this->entityManager
            ->getRDBRepository(StockMovement::ENTITY_TYPE)
            ->where($where)
            ->find();

        $sum = 0.0;
        foreach ($movements as $movement) {
            $sum += (float) ($movement->get('totalCost') ?? 0.0);
        }

        return round($sum, 2);
    }

    /**
     * @return array{openInvoiceCount: int, overdueInvoiceCount: int, openInvoiceBalance: float}
     */
    private function getInvoiceRisk(?string $clinicId): array
    {
        $where = [
            'deleted' => false,
            'status' => [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIAL_PAID],
        ];

        if ($clinicId !== null) {
            $where['clinicId'] = $clinicId;
        }

        /** @var iterable<Invoice> $invoices */
        $invoices = $this->entityManager
            ->getRDBRepository(Invoice::ENTITY_TYPE)
            ->where($where)
            ->find();

        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        $openCount = 0;
        $overdueCount = 0;
        $balance = 0.0;

        foreach ($invoices as $invoice) {
            $openCount++;
            $balance += max(0.0, $invoice->getBalance());

            $dueDate = (string) ($invoice->get('dueDate') ?? '');
            if ($dueDate !== '' && $dueDate < $today) {
                $overdueCount++;
            }
        }

        return [
            'openInvoiceCount' => $openCount,
            'overdueInvoiceCount' => $overdueCount,
            'openInvoiceBalance' => round($balance, 2),
        ];
    }

    /**
     * @param array{from: string, to: string} $period
     * @return array{
     *     totalAmount: float,
     *     draftAmount: float,
     *     approvedAmount: float,
     *     paidAmount: float,
     *     entryCount: int,
     *     rows: list<array{
     *         id: string,
     *         userId: string,
     *         userName: string,
     *         status: string,
     *         totalAmount: float,
     *         periodFrom: string,
     *         periodTo: string,
     *         sourceBreakdown: mixed
     *     }>
     * }
     */
    private function getPayrollSnapshot(array $period, ?string $clinicId, int $limit): array
    {
        $where = [
            'deleted' => false,
            'periodFrom<' => substr($period['to'], 0, 10),
            'periodTo>=' => substr($period['from'], 0, 10),
        ];

        if ($clinicId !== null) {
            $where['clinicId'] = $clinicId;
        }

        /** @var iterable<SalaryEntry> $entries */
        $entries = $this->entityManager
            ->getRDBRepository(SalaryEntry::ENTITY_TYPE)
            ->where($where)
            ->order('totalAmount', 'DESC')
            ->find();

        $summary = [
            'totalAmount' => 0.0,
            'draftAmount' => 0.0,
            'approvedAmount' => 0.0,
            'paidAmount' => 0.0,
            'entryCount' => 0,
            'rows' => [],
        ];

        foreach ($entries as $entry) {
            $status = $entry->getStatus();

            if ($status === SalaryEntry::STATUS_CANCELLED) {
                continue;
            }

            $amount = round($entry->getTotalAmount(), 2);
            $summary['totalAmount'] += $amount;
            $summary['entryCount']++;

            if ($status === SalaryEntry::STATUS_DRAFT) {
                $summary['draftAmount'] += $amount;
            } elseif ($status === SalaryEntry::STATUS_APPROVED) {
                $summary['approvedAmount'] += $amount;
            } elseif ($status === SalaryEntry::STATUS_PAID) {
                $summary['paidAmount'] += $amount;
            }

            if (count($summary['rows']) < $limit) {
                $summary['rows'][] = [
                    'id' => (string) $entry->getId(),
                    'userId' => (string) ($entry->get('userId') ?? ''),
                    'userName' => (string) ($entry->get('userName') ?: $entry->get('userId') ?: ''),
                    'status' => $status,
                    'totalAmount' => $amount,
                    'periodFrom' => (string) ($entry->get('periodFrom') ?? ''),
                    'periodTo' => (string) ($entry->get('periodTo') ?? ''),
                    'sourceBreakdown' => $entry->get('sourceBreakdown'),
                ];
            }
        }

        $summary['totalAmount'] = round($summary['totalAmount'], 2);
        $summary['draftAmount'] = round($summary['draftAmount'], 2);
        $summary['approvedAmount'] = round($summary['approvedAmount'], 2);
        $summary['paidAmount'] = round($summary['paidAmount'], 2);

        return $summary;
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

    /**
     * @param array{
     *     appointmentCount: int,
     *     noShowCount: int,
     *     cancellationCount: int,
     *     issueCount: int,
     *     noShowRate: float,
     *     cancellationRate: float,
     *     issueRate: float
     * } $row
     * @return array{
     *     appointmentCount: int,
     *     noShowCount: int,
     *     cancellationCount: int,
     *     issueCount: int,
     *     noShowRate: float,
     *     cancellationRate: float,
     *     issueRate: float
     * }
     */
    private function withAppointmentRates(array $row): array
    {
        $appointmentCount = max(0, $row['appointmentCount']);

        if ($appointmentCount === 0) {
            return $row;
        }

        $row['noShowRate'] = round($row['noShowCount'] / $appointmentCount * 100, 1);
        $row['cancellationRate'] = round($row['cancellationCount'] / $appointmentCount * 100, 1);
        $row['issueRate'] = round($row['issueCount'] / $appointmentCount * 100, 1);

        return $row;
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

    private function normalizeOptionalId(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function stockLevelRank(string $level): int
    {
        return match ($level) {
            Material::LEVEL_OUT => 4,
            Material::LEVEL_CRITICAL => 3,
            Material::LEVEL_LOW => 2,
            default => 1,
        };
    }
}
