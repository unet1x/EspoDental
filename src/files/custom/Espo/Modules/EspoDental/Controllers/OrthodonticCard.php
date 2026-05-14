<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Controllers\Record;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\EspoDental\Entities\OrthodonticCard as Card;
use Espo\Modules\EspoDental\Services\OrthodonticCardService;
use stdClass;

class OrthodonticCard extends Record
{
    public function postActionClose(Request $request): stdClass
    {
        $this->assertAccess();
        $data = $request->getParsedBody();
        $id = (string) ($data->id ?? '');
        $finalStatus = (string) ($data->finalStatus ?? Card::STATUS_COMPLETED);
        if ($id === '') {
            throw new BadRequest('id required');
        }
        /** @var OrthodonticCardService $service */
        $service = $this->injectableFactory->create(OrthodonticCardService::class);

        $card = $service->closeCard($id, $finalStatus);
        return (object) ['id' => $card->getId(), 'status' => $card->getStatus()];
    }

    public function postActionReopen(Request $request): stdClass
    {
        $this->assertAccess();
        $id = (string) ($request->getParsedBody()->id ?? '');
        if ($id === '') {
            throw new BadRequest('id required');
        }
        /** @var OrthodonticCardService $service */
        $service = $this->injectableFactory->create(OrthodonticCardService::class);

        $card = $service->reopenCard($id);
        return (object) ['id' => $card->getId(), 'status' => $card->getStatus()];
    }

    private function assertAccess(): void
    {
        if (!$this->user->isAdmin() && !$this->user->isRegular()) {
            throw new Forbidden();
        }
    }
}
