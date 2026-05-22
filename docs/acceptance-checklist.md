# EspoDental Acceptance Checklist

Last updated: 2026-05-22

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
- Create a shift template for multiple weekdays/date range, run
  `Generate Shifts`, and confirm matching `DoctorShift` records are created
  once for each selected weekday and linked to the template.
- Add an additional doctor shift later the same day and confirm those slots
  become available.
- Add a closed shift period and confirm overlapping slots are blocked.
- Add a cabinet-only closed shift period and confirm overlapping slots are not
  offered and direct appointment save returns a server conflict.
- Confirm an assistant linked on the selected doctor shift is copied to the
  saved appointment without exposing manual assistant selection in booking.
- Launch health questionnaire QR/token.
- Submit questionnaire on tablet/mobile-size viewport.
- Confirm the form rejects submission when any visible yes/no item is
  unanswered.
- Confirm signature is stored.
- Confirm questionnaire detail shows grouped localized answers, not raw JSON.
- Confirm a patient card with medical alert answers shows a visible warning.
- Confirm an expired or missing questionnaire shows a visible patient-card
  warning and the questionnaire action can be issued again.
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
- Confirm the visit service line form uses an expandable service catalog tree
  with service search and still writes the selected catalog service.
- Confirm service price is copied to visit line.
- Confirm material norms are copied into `VisitMaterialLine` rows.
- Confirm the inline material quantity editor in the visit material panel saves
  actual consumption during an active visit without opening the full form.
- Adjust material consumption before finish.
- Upload before/after visit photos.
- Confirm the patient card clinical files panel shows visit photos with the
  originating visit/date context.
- Confirm the patient card exposes questionnaire PDF and signature links from
  completed health questionnaires.
- Confirm the patient card Questionnaire Summary shows latest questionnaire
  answers grouped by schema, questionnaire date/version, alert/expired flags
  and generated PDF/signature files.
- Confirm patient-card photo and questionnaire relationship panels are
  read-oriented; photos are added from visits and questionnaires are issued
  through the QR/token action.
- Confirm the patient card Care Summary shows linked parent/manual guardian
  data, linked children and orthodontic cards with status/context.
- Confirm the patient card Tooth Chart History shows recent tooth-chart
  snapshots with source visit, doctor, dentition type and annotated-teeth
  count.
- Confirm the patient card CBCT / Orthanc panel shows visit and orthodontic
  imaging studies with source links, file links and Orthanc URL/UID context.
- Finish visit.
- Confirm appointment status is `finished`.
- Confirm stock write-off movements were created.
- Confirm stock write-off movements point to the prepared visit material lines.
- Confirm material current stock and stock level match the sum of stock
  movements and cannot be edited directly through the API.
- Confirm posted stock movements cannot be edited or deleted; corrections are
  entered as new receipt/write-off/adjustment movements.
- Confirm invoice and invoice lines were created.
- Confirm patient balance changed according to invoice.
- Confirm repeated finish does not duplicate invoice lines or stock movements.
- Confirm finish-visit downstream failures roll back the final visit and
  appointment statuses.
- Register payment.
- Confirm payment method is selected from the configured dropdown.
- Confirm draft, paid, cancelled/storno and over-balance invoice payments are
  rejected server-side.
- Confirm payment changes patient balance.
- Create a refund payment for a completed inbound payment; confirm it is a
  separate outbound correction linked through `refundOf` and the source
  payment remains posted.
- Confirm cumulative refund payment amounts cannot exceed the original
  payment amount.
- Try invoice storno while linked payments are still net-positive; confirm the
  server returns `Refund invoice payments before storno`.
- Confirm the patient card financial panel shows current balance, open invoice
  balance, unallocated credit, open invoices and recent payments.
- Print or generate invoice/act/receipt artifact.
- Book next appointment from patient/visit/invoice context.
- Confirm future appointments appear above past visits in the patient card
  history panel.
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
- Outbound external messages have `NotificationLog` audit rows with channel,
  direction, provider, recipient, status, attempts and external message id when
  the provider returns one.
- Bot/LLM proposals are stored in `AssistantActionProposal`; high-risk medical
  or financial proposals cannot become `applied` before a human-approved status.
- Local LLM virtual administrator uses only the MCP/Integration contract,
  creates `AssistantActionProposal(source=llm)` for state changes and refuses
  direct payment, visit-finish, medical-note, invoice-cancel and delete actions.
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
- Manager can open the doctor productivity dashlet and compare finished visits,
  service-line count, gross amount and average visit amount by doctor.
- Manager can open the cabinet utilization dashlet and compare appointment
  count, occupied hours, available hours and utilization percent by cabinet.
- Manager can open the no-show and cancellations dashlet and compare total
  appointments, no-shows, cancellations and issue percent by doctor.
- Stock manager can manage materials and acknowledge low-stock alerts.
- Confirm each EspoDental role or role-team user receives the matching
  dashboard template after bootstrap unless a user-specific dashboard template
  was already set.

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
- Proxmox VM restore runbook includes database dump, uploads restore, module
  revision, rebuild/bootstrap, verification and rollback.
