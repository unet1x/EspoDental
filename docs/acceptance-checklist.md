# EspoDental Acceptance Checklist

Last updated: 2026-05-14

Use this checklist before calling any vertical slice complete.

Known regression notes from 2026-05-21 are documented in
`docs/regression-handoff-2026-05-21.md`. Review that handoff before accepting
calendar booking, appointment quick-create, visit finish, payment or appointment
status behavior.

## 1. End-To-End Patient Flow

Preconditions:

- clean or known test database;
- `espo-dental-bootstrap` has been run;
- at least one doctor, administrator and cabinet exist;
- at least one service has material norms.

Checklist:

- Create `PreliminaryPatient` with required name and phone fields.
- Confirm reception create/detail screens do not show technical conversion,
  assigned user, teams or questionnaire-token noise.
- Confirm clinic is auto-filled from the EspoDental default clinic setting or
  from the only active clinic.
- Confirm admin-only EspoDental settings show editable dictionaries for payment
  methods, tooth-chart conditions/colors and tooth-surface labels.
- Book preliminary patient into a free cabinet/time slot.
- Confirm the appointment start field can load and apply free slots after
  doctor, cabinet, date and duration are selected.
- Confirm appointment create/edit does not show a separate "Free slots" button,
  manual start datetime inputs, or manual assistant picker.
- Confirm appointment clinic is prefilled from the module default clinic.
- Confirm free-slot search does not offer a time where the selected doctor is
  already booked in another cabinet.
- Confirm free-slot search does not offer a time where the selected patient or
  preliminary patient already has an overlapping appointment.
- Confirm the patient detail header has `Записать на прием` and opens the short
  appointment modal with the patient prelinked.
- Confirm the patient `Записи` relationship panel does not show a plus/create
  flow for booking.
- Confirm the booking modal does not show `Расширенная форма`, assistant,
  manual start datetime, assigned user or teams.
- Confirm changing appointment duration to 1h/1.5h/2h changes the free-slot end
  time accordingly.
- Confirm free-slot labels and the patient booking success notification show
  clinic-local time, while the saved payload remains UTC.
- Confirm conflicting doctor/cabinet/patient slot is rejected.
- Create a doctor shift for the selected doctor and confirm free slots outside
  that shift are not offered.
- Add an additional doctor shift later the same day and confirm those slots
  become available.
- Add a closed shift period and confirm overlapping slots are blocked.
- Confirm an assistant linked on the selected doctor shift is copied to the
  saved appointment without exposing manual assistant selection in booking.
- Launch health questionnaire QR/token.
- Submit questionnaire on tablet/mobile-size viewport.
- Confirm the form rejects submission when any visible yes/no item is
  unanswered.
- Confirm signature is stored.
- Convert preliminary patient to `Patient`.
- Confirm the converted preliminary patient is hidden from the normal
  preliminary patient list.
- Confirm patient contains copied personal fields.
- Confirm converted patient links back to preliminary patient.
- Confirm patient balance starts at `0`.
- Confirm normal user cannot create `Patient` directly.
- Start visit from appointment.
- Confirm visit cannot start from preliminary patient.
- Add service line from catalog.
- Confirm service price is copied to visit line.
- Confirm material norms are copied into `VisitMaterialLine` rows.
- Adjust material consumption before finish.
- Upload before/after visit photos.
- Finish visit.
- Confirm appointment status is `finished`.
- Confirm stock write-off movements were created.
- Confirm stock write-off movements point to the prepared visit material lines.
- Confirm invoice and invoice lines were created.
- Confirm patient balance changed according to invoice.
- Register payment.
- Confirm payment method is selected from the configured dropdown.
- Confirm draft, paid, cancelled/storno and over-balance invoice payments are
  rejected server-side.
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
- Questionnaire PDF contains answers in two columns and includes the stored
  signature.

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
- After editing questionnaire schema, `php rebuild.php` and app timestamp
  update have been run before browser verification.

## 5. Migration/Infrastructure Checks

- Data directories are outside containers.
- Database backup can be restored to staging.
- Uploaded files/photos survive container recreation.
- Module source mount can be updated by git pull + rebuild.
- Synology deployment notes are current.
- Proxmox VM deployment notes are current before migration.
