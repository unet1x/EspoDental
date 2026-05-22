<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Exceptions\Forbidden;
use Espo\Entities\User;
use Espo\Modules\EspoDental\Services\ReportService;

class Report
{
    public function __construct(
        private readonly ReportService $reportService,
        private readonly User $user
    ) {
    }

    /**
     * @throws Forbidden
     * @return list<array{label: string, value: float, year: int, month: int}>
     */
    public function getActionMonthlyRevenue(Request $request): array
    {
        $this->assertReportAccess();
        $months = (int) ($request->getQueryParam('monthsBack') ?? 12);
        if ($months < 1 || $months > 36) {
            $months = 12;
        }
        return $this->reportService->getMonthlyRevenue($months);
    }

    /**
     * @throws Forbidden
     * @return array{open: int, overdue: int, paidThisMonth: float}
     */
    public function getActionInvoiceSummary(Request $request): array
    {
        $this->assertReportAccess();
        return $this->reportService->getInvoiceSummary();
    }

    /**
     * @throws Forbidden
     * @return array{open: int, critical: int}
     */
    public function getActionLowStockSummary(Request $request): array
    {
        $this->assertReportAccess();
        return $this->reportService->getLowStockSummary();
    }

    /**
     * @throws Forbidden
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
    public function getActionDoctorProductivity(Request $request): array
    {
        $this->assertReportAccess();

        return $this->reportService->getDoctorProductivity(
            $request->getQueryParam('dateFrom'),
            $request->getQueryParam('dateTo'),
            (int) ($request->getQueryParam('limit') ?? 10)
        );
    }

    /**
     * @throws Forbidden
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
    public function getActionCabinetUtilization(Request $request): array
    {
        $this->assertReportAccess();

        return $this->reportService->getCabinetUtilization(
            $request->getQueryParam('dateFrom'),
            $request->getQueryParam('dateTo'),
            (int) ($request->getQueryParam('workStartHour') ?? 8),
            (int) ($request->getQueryParam('workEndHour') ?? 21),
            $request->getQueryParam('clinicId'),
            (int) ($request->getQueryParam('limit') ?? 20)
        );
    }

    /**
     * @throws Forbidden
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
    public function getActionNoShowCancellations(Request $request): array
    {
        $this->assertReportAccess();

        return $this->reportService->getNoShowCancellations(
            $request->getQueryParam('dateFrom'),
            $request->getQueryParam('dateTo'),
            $request->getQueryParam('clinicId'),
            (int) ($request->getQueryParam('limit') ?? 10)
        );
    }

    /**
     * @throws Forbidden
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
    public function getActionInventoryStatus(Request $request): array
    {
        $this->assertReportAccess();

        return $this->reportService->getInventoryStatus(
            $request->getQueryParam('dateFrom'),
            $request->getQueryParam('dateTo'),
            $request->getQueryParam('clinicId'),
            (int) ($request->getQueryParam('limit') ?? 15)
        );
    }

    /**
     * @throws Forbidden
     */
    private function assertReportAccess(): void
    {
        if (!$this->user->isAdmin() && !$this->user->isRegular()) {
            throw new Forbidden();
        }
    }
}
