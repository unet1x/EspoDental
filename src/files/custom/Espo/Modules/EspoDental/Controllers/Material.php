<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Controllers\Record;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\EspoDental\Entities\Material as MaterialEntity;
use stdClass;

class Material extends Record
{
    public function patchActionUpdate(Request $request, Response $response): stdClass
    {
        $this->assertDerivedStockIsNotChanged($request);

        return parent::patchActionUpdate($request, $response);
    }

    public function putActionUpdate(Request $request, Response $response): stdClass
    {
        $this->assertDerivedStockIsNotChanged($request);

        return parent::putActionUpdate($request, $response);
    }

    private function assertDerivedStockIsNotChanged(Request $request): void
    {
        $data = $request->getParsedBody();
        if (!is_object($data)) {
            return;
        }

        if (!property_exists($data, 'currentStock') && !property_exists($data, 'stockLevel')) {
            return;
        }

        $id = $request->getRouteParam('id');
        if (!$id) {
            return;
        }

        /** @var MaterialEntity|null $material */
        $material = $this->entityManager->getEntityById(MaterialEntity::ENTITY_TYPE, $id);
        if (!$material) {
            throw new NotFound('Material not found');
        }

        if (
            property_exists($data, 'currentStock') &&
            round((float) $data->currentStock, 4) !== round($material->getCurrentStock(), 4)
        ) {
            throw new Conflict('Material stock is derived from StockMovement records');
        }

        if (
            property_exists($data, 'stockLevel') &&
            (string) $data->stockLevel !== (string) $material->get('stockLevel')
        ) {
            throw new Conflict('Material stock is derived from StockMovement records');
        }
    }
}
