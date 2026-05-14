# EspoDental Acceptance Checklist

Last updated: 2026-05-14

Use this checklist before calling any vertical slice complete.

## 1. End-To-End Patient Flow

Preconditions:

- clean or known test database;
- `espo-dental-bootstrap` has been run;
- at least one doctor, administrator and cabinet exist;
- at least one service has material norms.

Checklist:

- Create `PreliminaryPatient` with required name and phone fields.
- Book preliminary patient into a free cabinet/time slot.
- Confirm conflicting doctor/cabinet/patient slot is rejected.
- Launch health questionnaire QR/token.
- Submit questionnaire on tablet/mobile-size viewport.
- Confirm signature is stored.
- Convert preliminary patient to `Patient`.
- Confirm patient contains copied personal fields.
- Confirm converted patient links back to preliminary patient.
- Confirm patient balance starts at `0`.
- Confirm normal user cannot create `Patient` directly.
- Start visit from appointment.
- Confirm visit cannot start from preliminary patient.
- Add service line from catalog.
- Confirm service price is copied to visit line.
- Confirm material norms are copied.
- Adjust material consumption before finish.
- Upload before/after visit photos.
- Finish visit.
- Confirm appointment status is `completed`.
- Confirm stock write-off movements were created.
- Confirm invoice and invoice lines were created.
- Confirm patient balance changed according to invoice.
- Register payment.
- Confirm payment changes patient balance.
- Print or generate invoice/act/receipt artifact.
- Book next appointment from patient/visit/invoice context.
- Confirm visit, invoice, payment, photos and next appointment are visible in
  the patient card.

## 2. Data Integrity Checks

- No `Patient` record exists without either conversion source or migration
  source.
- No `Visit` exists without `Appointment`.
- No `Invoice` exists without `Visit`.
- No stock quantity change exists without `StockMovement`.
- Posted invoice/payment/stock movement cannot be silently edited by ordinary
  users.
- Appointment status changes have `AppointmentStatusLog` rows.
- Questionnaire older than 1 year shows a patient alert.

## 3. Role Checks

- Administrator can manage preliminary patients, appointments, questionnaire
  launch, conversion, invoices and payments.
- Administrator cannot edit finished clinical notes without privileged rights.
- Doctor can see and start own visits.
- Doctor can edit active visit clinical data.
- Doctor cannot post or reverse payments.
- Assistant can add visit photos/material details where allowed.
- Manager can view reports and financial summaries.
- Stock manager can manage materials and acknowledge low-stock alerts.

## 4. Technical Checks

- `php rebuild.php` passes.
- `php command.php espo-dental-bootstrap` is idempotent.
- PHP syntax check passes for module files.
- JSON metadata parses.
- Docker smoke test passes.
- Calendar API returns expected cabinets and appointments.
- Report API returns expected data shape.
- Browser UI loads without visible route/controller errors.
- Application log has no new critical errors from the tested flow.

## 5. Migration/Infrastructure Checks

- Data directories are outside containers.
- Database backup can be restored to staging.
- Uploaded files/photos survive container recreation.
- Module source mount can be updated by git pull + rebuild.
- Synology deployment notes are current.
- Proxmox VM deployment notes are current before migration.
