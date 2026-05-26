# SimpleStom Cash Desk Contract

Last updated: 2026-05-27

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
- selected doctor filter contract for the feedback screen, enforced through the
  invoice's linked `Visit.doctor`;
- selected-invoice action panel with patient, doctor, visit, totals and balance;
- payment wizard entry point from the selected invoice panel;
- shift closing totals instead of the old day-balance concept.

## 2026-05-27 Feedback Pass

The cash desk workspace now keeps the invoice list and the selected-invoice
actions on the same operational surface:

- `CashDeskService::getWorkspace` returns `doctorOptions`, applies `doctorId`
  through the linked visit and returns `selectedInvoice`;
- the dashlet exposes an explicit doctor selector, preserves unpaid-only
  filtering and highlights the selected invoice row;
- the selected-invoice panel opens the invoice, patient or visit record and
  enables `Принять оплату` only when the invoice is payable;
- the payment dialog posts directly to `Payment/action/accept` with the selected
  invoice context.

Browser smoke after `update-app-timestamp` confirmed the local dashboard cash
desk renders the doctor selector and selected-invoice panel. Before the payable
fixture existed, the seeded demo had only paid invoices, so the smoke verified
that payment is disabled for a paid invoice instead of creating a new financial
mutation.

The demo seed also now creates a separate payable open invoice marked
`DEMO SimpleStom payable invoice for cash desk wizard.` so browser acceptance can
open the payment wizard and cancel it without posting a payment.

After rerunning `espo-dental-demo-seed`, browser smoke confirmed unpaid-only
cash desk shows the payable invoice, the selected invoice panel enables
`Принять оплату`, the wizard opens with the invoice balance and configured
methods, and cancel closes the dialog without creating a payment.

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
