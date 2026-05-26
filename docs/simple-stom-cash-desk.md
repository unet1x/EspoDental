# SimpleStom Cash Desk Contract

Last updated: 2026-05-26

Stage 11 aligns EspoDental finance workflows with the SimpleStom cash desk
feedback screen.

## Workspace

The cash desk starts from invoices, not from an abstract payment list.

Runtime pieces:

- dashlet: `CashDeskWorkspace`;
- endpoint: `GET /EspoDental/CashDesk/workspace`;
- close-shift endpoint: `POST /EspoDental/CashDesk/closeShift`;
- service: `CashDeskService`.

The workspace exposes:

- invoice list with unpaid-only filtering;
- selected doctor filter contract for the feedback screen;
- payment action entry point;
- shift closing totals instead of the old day-balance concept.

## Payments And Advances

`Payment` supports SimpleStom methods:

- `cash`;
- `card`;
- `crypto`;
- `advance`.

Crypto payments can store `cryptoAsset`, `cryptoAmount` and
`externalReference`. Advance application is explicit:

1. create an invoice-linked inbound payment with method `advance`;
2. create an unallocated outbound advance-debit payment;
3. let existing invoice and patient-balance recalculation hooks update totals.

Refund and storno workflows remain correction-by-new-record flows. Posted
payments are not mutated.

## Shift Closing

`CashShift` records a closed cash period with cashier, clinic, period bounds,
cash/card/crypto/advance totals and invoice-applied total. Closing a shift
creates a new record from `CashDeskService::closeShift`; it does not rewrite
existing payments.

## Adjustments And Documents

`FinancialAdjustment` stores write-off, complaint and manual-charge corrections
with a required reason and signed amount.

`FinancialDocumentSequence` stores per-clinic document numbering for invoice,
act and receipt documents using the SimpleStom prefix plus padded-number
contract.
