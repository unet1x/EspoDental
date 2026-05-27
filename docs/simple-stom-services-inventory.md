# SimpleStom Services And Inventory Contract

Last updated: 2026-05-27

Stage 10 brings the SimpleStom service catalog and lot-aware inventory contract
into the EspoDental module.

## Service Catalog

EspoDental keeps the existing catalog entities:

- `ServiceCategory`;
- `Service`;
- `ServiceMaterial`.

`Service` already stores category, price, duration and color. Stage 10 adds a
`cabinetRequirements` JSON contract so booking and slot filtering can express
equipment or room requirements without replacing the existing `Cabinet` model.

The current JSON-backed requirement contract is intentionally small:

- `equipmentAny`: at least one token must be found in cabinet name, code,
  equipment or description;
- `equipmentAll` / `requiredEquipment`: every token must be present;
- `cabinetIds`: explicit compatible cabinet ids;
- `cabinetCodes`: explicit compatible cabinet codes.

The shared matcher is
`src/files/custom/Espo/Modules/EspoDental/Tools/CabinetRequirementMatcher.php`.
The bootstrap service catalog now seeds basic equipment requirements for
orthodontics, hygiene, therapy, surgery, orthopedics and implant consultation.

`ServiceMaterial` remains the service material norm table and now records:

- required quantity;
- unit;
- whether the material is mandatory for the service.

Historical invoices and finished visit service lines continue to store their
own price/amount values, so later catalog price changes do not rewrite history.

## Materials

`Material` now exposes SimpleStom unit semantics:

- `consumptionUnit`;
- `purchasingUnit`;
- `conversionFactor`;
- `trackExpiration`;
- `reorderUrl`.

The existing `unit` field remains for backward compatibility and maps to
`consumptionUnit` in stock calculations.

## Warehouses And Lots

Stage 10 adds the warehouse and stock-lot layer that SimpleStom requires:

- `InventoryWarehouse`: main clinic warehouse or satellite cabinet warehouse;
- `InventoryStockLot`: material lot in a warehouse with purchasing-unit
  quantity, lot number, expiration date and source transaction;
- `StockMovement`: source/target warehouse and stock-lot links.

The bootstrap seeder creates one main warehouse per clinic and one satellite
warehouse per active cabinet. Opening stock receipts are linked to the main
warehouse and create an `OPENING` stock lot.

## FEFO And Corrections

`InventoryService::planFefoConsumption` returns the lot plan for write-off in
first-expire-first-out order:

1. earliest `expiresAt`;
2. earliest `receivedAt`;
3. lots without expiration after dated lots.

Receipt validation blocks missing expiration dates for materials that track
expiration. Manual corrections and write-offs require a reason. Posted
stock-movement immutability remains unchanged: fixes are represented by a new
correction movement, not by editing the old movement.

## Inventory Workspace

Pass 4 starts the dedicated operational stock surface from
`16-feedback-inventory.png`.

Runtime pieces:

- dashlet: `InventoryWorkspace`;
- endpoint: `GET /EspoDental/Inventory/workspace`;
- write endpoints:
  - `POST /EspoDental/Inventory/receipt`;
  - `POST /EspoDental/Inventory/transfer`;
  - `POST /EspoDental/Inventory/writeOff`;
  - `POST /EspoDental/Inventory/adjustment`;
- service payload: `InventoryService::getWorkspace`.

It keeps the existing `InventoryStatus` report dashlet as a manager summary,
while the stock-role dashboard gets a primary workspace that shows:

- active main and cabinet warehouses;
- active lots for the selected warehouse;
- expiring and expired lots;
- low-stock rows with open alert counts;
- future-order candidates from low stock, max stock and reorder URL;
- cabinet issue movements;
- recent immutable stock movements.

The second inventory workspace slice adds write flows from the same workspace:

- receipt creates a `receipt` movement and a linked `InventoryStockLot`;
- transfer creates paired `transfer_out` and `transfer_in` movements so clinic
  material stock is not distorted by moving goods between warehouses;
- write-off decrements a lot and creates a reasoned `writeoff` movement;
- adjustment supports inventory count, manual increase and manual decrease by
  creating new correction movements.

Posted `StockMovement` rows remain immutable. Workspace write actions update
current lot balances where needed, but corrections are represented by new
movement rows with reason and source warehouse/lot context.

Write request contracts:

- receipt requires `warehouseId`, `materialId` and positive `quantity`; it also
  accepts `lotNumber`, `expiresAt`, `receivedAt`, `unitPrice`, `reason` and
  optional `clinicId`. Materials with expiration tracking require `expiresAt`.
- transfer requires `stockLotId`, `targetWarehouseId` and positive `quantity`.
  The source and target warehouses must be different and belong to the same
  clinic.
- write-off requires `stockLotId`, positive `quantity` and a non-empty
  `reason`.
- adjustment requires a non-empty `reason`. `adjustmentType`/`mode` can be
  `set`, `increase`, `decrease`, `manual_increase` or `manual_decrease`.
  `set` uses `stockLotId` plus `countedQuantity`; increase uses
  `warehouseId`, `materialId` and positive `quantity`; decrease uses
  `stockLotId` and positive `quantity`.

Every write response returns the affected movement/lot ids and a refreshed
`workspace` payload for the relevant warehouse so the dashlet can redraw without
editing historical movement rows client-side.
