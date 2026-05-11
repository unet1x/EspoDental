<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Entities\User;
use Espo\Modules\EspoDental\Entities\OrthodonticCard as Card;
use Espo\Modules\EspoDental\Services\OrthodonticCardService;
use stdClass;

class OrthodonticCard
{
    public function __construct(
        private readonly OrthodonticCardService $service,
        private readonly User $user
    ) {
    }

    public function postActionClose(Request $request): stdClass
    {
        $this->assertAccess();
        $data = $request->getParsedBody();
        $id = (string) ($data->id ?? '');
        $finalStatus = (string) ($data->finalStatus ?? Card::STATUS_COMPLETED);
        if ($id === '') {
            throw new BadRequest('id required');
        }
        $card = $this->service->closeCard($id, $finalStatus);
        return (object) ['id' => $card->getId(), 'status' => $card->getStatus()];
    }

    public function postActionReopen(Request $request): stdClass
    {
        $this->assertAccess();
        $id = (string) ($request->getParsedBody()->id ?? '');
        if ($id === '') {
            throw new BadRequest('id required');
        }
        $card = $this->service->reopenCard($id);
        return (object) ['id' => $card->getId(), 'status' => $card->getStatus()];
    }

    private function assertAccess(): void
    {
        if (!$this->user->isAdmin() && !$this->user->isRegular()) {
            throw new Forbidden();
        }
    }
}
