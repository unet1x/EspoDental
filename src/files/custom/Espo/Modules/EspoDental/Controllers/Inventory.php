<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Exceptions\Forbidden;
use Espo\Entities\User;
use Espo\Modules\EspoDental\Services\InventoryService;

class Inventory
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly User $user
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getActionWorkspace(Request $request): array
    {
        $this->assertAccess();

        return $this->inventoryService->getWorkspace(
            $request->getQueryParam('clinicId') ? (string) $request->getQueryParam('clinicId') : null,
            $request->getQueryParam('warehouseId') ? (string) $request->getQueryParam('warehouseId') : null,
            (int) ($request->getQueryParam('limit') ?? 20)
        );
    }

    private function assertAccess(): void
    {
        if (!$this->user->isAdmin() && !$this->user->isRegular()) {
            throw new Forbidden();
        }
    }
}
