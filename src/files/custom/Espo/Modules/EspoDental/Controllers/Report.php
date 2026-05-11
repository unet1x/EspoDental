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
     */
    private function assertReportAccess(): void
    {
        if (!$this->user->isAdmin() && !$this->user->isRegular()) {
            throw new Forbidden();
        }
    }
}
