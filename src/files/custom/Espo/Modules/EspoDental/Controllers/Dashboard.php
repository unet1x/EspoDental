<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Exceptions\Forbidden;
use Espo\Entities\User;
use Espo\Modules\EspoDental\Services\DashboardActionCenterService;

class Dashboard
{
    public function __construct(
        private readonly DashboardActionCenterService $dashboardActionCenterService,
        private readonly User $user
    ) {
    }

    /**
     * @throws Forbidden
     * @return array<string, mixed>
     */
    public function getActionActionCenter(Request $request): array
    {
        $this->assertAccess();

        $clinicId = $request->getQueryParam('clinicId');

        return $this->dashboardActionCenterService->getActionCenter(
            $this->user,
            $clinicId !== null && $clinicId !== '' ? (string) $clinicId : null,
            (int) ($request->getQueryParam('limit') ?? 8)
        );
    }

    /**
     * @throws Forbidden
     */
    private function assertAccess(): void
    {
        if (!$this->user->isAdmin() && !$this->user->isRegular()) {
            throw new Forbidden();
        }
    }
}
