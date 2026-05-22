# EspoDental Current State

Last updated: 2026-05-22

This file is the handoff document for future development sessions. It describes
what has been verified, what exists in metadata, and what still needs product
acceptance.

## 0. Regression Handoff Closed 2026-05-21

A focused regression handoff was added after reviewing the latest front-desk
and booking changes:

- `docs/regression-handoff-2026-05-21.md`

The current `main` branch contains the handoff fixes:

- resource-calendar appointment payloads include clinic-local display times and
  timezone while preserving UTC storage fields;
- resource-calendar drag/drop and resize submit local time plus timezone to the
  server for UTC persistence;
- global `Appointment` quick-create is removed from the workspace seeder;
- `finishVisit` runs invoice and stock work inside an EspoCRM database
  transaction before final statuses and accepts repeated calls for
  already-finished visits;
- `PaymentService::accept` enforces invoice status, patient, clinic and
  over-balance guards server-side;
- appointment final status is consistently `finished` in code, docs and grid
  colors.

`tests/RegressionHandoff20260521Test.php` locks these decisions with
structural regression checks. Keep the handoff document for historical context
before changing calendar booking, appointment quick-create, visit finish,
payment or appointment status behavior again.

## 1. Repository And Runtime

- Repository root: `/Users/unet1x/Codex/EspoDental`.
- Local Docker stack: `deploy/local/docker-compose.yml`.
- Local URL: `http://localhost:18080`.
- Local admin credentials used for verification: `admin` /
  `espodental-admin`.
- Target EspoCRM version: 9.2.7.
- Module is mounted into the EspoCRM container, so local source changes can be
  tested with `rebuild.php`.

## 2. Verified Baseline

The following checks were completed on 2026-05-14:

- `php rebuild.php` inside the local EspoCRM container passed.
- `php command.php espo-dental-bootstrap` runs and is idempotent.
- 129 module PHP files passed `php -l`.
- 305 JSON files parsed successfully.
- Docker smoke stack booted and passed `deploy/smoke/smoke.sh`.
- All 31 entity API list endpoints responded successfully during verification.
- `EspoDental/Calendar/appointments` responded with cabinets.
- `EspoDental/Report/monthlyRevenue` responded with monthly data.
- Local UI loaded with Russian EspoDental menu and dashboard.
- Compose config validation passed for local, staging and production templates.
- Phase 1 front-desk API flow passed on the local Docker stack:
  `PreliminaryPatient` -> `Appointment` -> questionnaire token/QR -> public
  questionnaire submit -> automatic `Patient` conversion -> appointment
  re-parenting -> `Start Visit`.
- Direct `POST /Patient` is blocked with `403` for normal API creation.

PHPUnit was not run in the local container because composer/phpunit are not
installed there. Host-side `vendor/bin/phpunit` was run on 2026-05-22 with
PHP 8.5.6 and passed: 326 tests, 3986 assertions.

## 3. Existing Entity Scopes

Current module metadata contains:

- `Appointment`
- `AppointmentStatusLog`
- `Cabinet`
- `CephalometricMeasurement`
- `Clinic`
- `HealthQuestionnaire`
- `Invoice`
- `InvoiceLine`
- `LowStockAlert`
- `Material`
- `MaterialCategory`
- `NotificationLog`
- `OrthoPhoto`
- `OrthodonticCard`
- `Patient`
- `Payment`
- `PreliminaryPatient`
- `QuestionnaireToken`
- `SalaryBonus`
- `SalaryEntry`
- `SalaryProfile`
- `Service`
- `ServiceCategory`
- `ServiceMaterial`
- `Settings`
- `StockMovement`
- `ToothChartSnapshot`
- `ToothMovementPlan`
- `TreatmentStage`
- `Visit`
- `VisitPhoto`
- `VisitServiceLine`

## 4. Bootstrap State

`espo-dental-bootstrap` prepares the visible workspace:

- teams and roles;
- one default clinic;
- 5 cabinets;
- Russian starter service categories;
- starter service catalog;
- starter material categories and materials;
- opening stock movements;
- scheduled jobs;
- dashboard template;
- role-specific dashboard templates for administrator, doctor, assistant,
  manager and stock workspaces;
- menu, quick-create list, calendar settings and base currency.
- category-first catalog menu: the left navigation shows service categories as
  `Каталог услуг` and material categories as `Материалы`; direct `Service` and
  `Material` tabs are hidden from the normal workspace.
- backfill helpers repair old demo/local names for clinical lines, visits and
  tooth charts during bootstrap.
- backfill helper sets `isChild` for existing patients whose birth date gives
  age 0-14.

This is enough for a first usable workspace, but it is not a full accepted MIS
workflow yet.

## 5. Phase 1 Front Desk Intake

Implemented in branch `feature-front-desk-intake`:

- `Patient` create button is disabled in client metadata.
- `Patient` creation is guarded by a hook and allowed only from the module
  conversion service.
- `PreliminaryPatient` can issue a health-questionnaire QR/token before a
  patient record exists.
- `QuestionnaireToken` and `HealthQuestionnaire` can belong to a
  `PreliminaryPatient` before conversion.
- Public questionnaire submit stores the questionnaire and automatically
  converts the preliminary patient.
- Conversion copies personal fields, initializes patient balance to `0`,
  links questionnaire to the new patient and marks questionnaire state.
- Existing appointments linked to the preliminary patient are re-parented to
  the new patient.
- `Appointment` stores `bookedBy` automatically.
- Booking a preliminary patient moves their status to `booked`.
- Appointment conflict checks now include the patient/preliminary patient, not
  only doctor and cabinet.
- `Start Visit` requires a real `Patient` with completed, non-expired
  questionnaire.
- The operational `PreliminaryPatient` layout is simplified for reception:
  status, assigned user, teams, conversion internals, questionnaire token
  links and other technical panels are hidden.
- `PreliminaryPatient.phone` is required in the UI metadata.
- `PreliminaryPatient.status` defaults to `entered` when omitted.
- `PreliminaryPatient.clinic` is assigned automatically from the
  EspoDental default clinic setting. If the setting is empty and exactly one
  active clinic exists, that clinic is used as the fallback.
- EspoDental settings now expose a default clinic field under the module admin
  settings page.
- After successful questionnaire conversion, the originating preliminary
  patient is hidden from operational lists by marking it deleted while keeping
  the patient-side conversion link for reporting.
- `QuestionnaireToken` links are not shown in the normal patient/preliminary
  patient workspace.
- Health questionnaire submission requires every visible yes/no medical item
  to have an answer. Signature is still required.
- Health questionnaire PDF output uses a compact two-column answer layout.
- Direct `Visit` creation is disabled in client metadata and guarded by a hook.
  New visits are allowed through `AppointmentService::startVisit`.
- Direct `Invoice` creation is disabled in client metadata and guarded by a
  hook. New invoices are allowed through `InvoiceService` from a visit.
- `VisitMaterialLine` has been added as the prepared material-consumption
  record for a visit. When a `VisitServiceLine` is saved, active service
  material norms are copied into visit material lines with planned quantity,
  actual editable quantity, unit, unit price and total cost.
- `StockService::consumeForVisit` now uses prepared `VisitMaterialLine` rows
  when they exist, and falls back to legacy `ServiceMaterial` norms only for
  older visits without prepared material lines.
- `StockMovement` can link back to `sourceVisitMaterialLine`, so inventory
  write-off can be traced to the exact prepared consumption row.
- EspoDental role seeding now patches missing scope rows into existing module
  roles, so new scopes such as `VisitMaterialLine` are added on bootstrap
  without requiring role deletion/recreation.
- `Appointment` quick forms are simplified for reception: doctor/cabinet,
  date/free-time slot, duration, clinic, confirmation/reminder and complaints.
  End time, source, assigned user, teams, parent and status are hidden from
  quick booking. Assistant is not chosen manually in appointment forms; it is
  reserved for the future doctor/assistant schedule pairing.
- Patient detail has a primary `Записать на прием` action in the header. It
  opens the short appointment modal, prelinks the patient as
  `Appointment.parent`, and disables the generic full-form escape hatch.
- The patient `appointments` relationship panel no longer exposes create/select
  actions. Operational booking should go through the header action, avoiding
  the ambiguous small plus button.
- `Appointment.clinic` is prefilled in the booking UI from the EspoDental
  default clinic setting, or from the first available clinic when the setting
  is empty.
- `Appointment.dateStart` uses a slot-picker helper in edit forms and hides the
  native datetime inputs and the technical "free slots" action button. After
  doctor, cabinet, date and duration are known, it calls
  `EspoDental/Calendar/freeSlots` and lets reception pick only a free time from
  the dropdown. Existing appointment edits exclude the current appointment from
  the occupied intervals so its own slot does not block rescheduling.
- The slot picker reads the live value from EspoCRM's duration field because
  the standard duration field does not persist its selected seconds into
  `Appointment.duration` until save. Free-slot end times therefore follow the
  selected 15m/30m/45m/1h/1.5h/2h duration immediately.
- Free-slot API responses include UTC storage values plus clinic-local
  `localStart`/`localEnd` values. The slot picker shows clinic-local time,
  saves the exact UTC slot returned by the API, and the patient booking success
  notification includes the selected clinic-local time.
- Appointment display names are formatted in the clinic timezone, while stored
  `dateStart`/`dateEnd` remain UTC.
- Free-slot search builds occupancy independently for cabinet, doctor and
  patient. A slot is not offered if the selected doctor is already booked in
  another cabinet, or if the selected patient/preliminary patient already has
  an overlapping blocking appointment.
- `Appointment.dateEnd` is derived from `dateStart + duration` before conflict
  checks. Direct manual selection of `in_progress` and `finished` appointment
  statuses is blocked; start/finish visit services own those statuses.
- Visit names are built from the linked patient full name and visit date.
- `VisitServiceLine` price, currency and VAT always come from the selected
  `Service`. Doctor-facing layouts hide unit price, currency, VAT and
  tooth-number fields.
- `VisitMaterialLine` unit, unit price/currency and total cost always come from
  the selected `Material`. Doctor-facing layouts show material, planned
  quantity, actual quantity and unit, not cost fields.
- `VisitMaterialLine.quantity` has a compact inline quantity editor in the
  `Visit` material relationship panel while the parent visit is
  `in_progress`; it PATCHes only the actual consumption and keeps finished
  visits read-only through the existing server guard.
- `Material.currentStock` and `Material.stockLevel` are derived from
  `StockMovement` rows. Direct server-side edits to these fields are rejected;
  new materials start at zero/out until receipt or adjustment movements change
  the derived balance.
- Posted `StockMovement` records are immutable in normal flows. Managers and
  stock managers can create correction movements, but role bootstrap now
  force-patches existing EspoDental roles so stock movements are not edited or
  deleted silently after posting.
- `Finish Visit` button visibility is tied to `Visit.status = in_progress`;
  completed visits no longer show a misleading finish action after metadata
  refresh.
- `VisitService::finishVisit` is wrapped in
  `EntityManager::getTransactionManager()->run(...)`, so invoice creation,
  stock write-off and final visit/appointment status changes commit or roll
  back together. The downstream steps still run before statuses and remain
  idempotent for repeated calls.
- `Visit` detail layout is cleaned for the doctor workspace: status,
  appointment, assigned user, teams, stream and invoice relationship panels are
  removed from the main doctor view, while created/modified timestamps are kept
  in the side panel.
- `AppointmentService::startVisit` creates a tooth-chart snapshot immediately.
  Existing visits without a snapshot get one lazily through
  `GET /Visit/action/toothChart`.
- Visit detail has a visual tooth-chart preview with an edit link while the
  visit is in progress.
- `VisitPhoto` quick-add forms default name, patient and recorded date from the
  visit context.
- `VisitPhoto` quick-add forms no longer show the recorded date field to the
  doctor; the hook sets current date/time automatically.
- `VisitServiceLine` edit forms add an expandable service catalog tree above
  the hidden generic service link field. Doctors can search by service/category
  name or code, expand categories and select a service while the UI still
  writes the normal `serviceId/serviceName` link and reuses existing
  price/material logic.
- `VisitServiceLine` and `VisitMaterialLine` hooks reject save/remove attempts
  after the parent visit is no longer `in_progress`.
- `Patient` and `PreliminaryPatient` manual delete actions are hidden in
  client metadata and guarded server-side. Preliminary conversion remains the
  controlled path that removes/hides the source preliminary patient.
- `Patient` now has an optional linked parent patient relationship in addition
  to manual parent/guardian fields. Date of birth 0-14 years inclusive sets
  `isChild` automatically on save.
- Child appointment reminders resolve delivery contact/preferences from the
  linked parent patient when present; otherwise they use the child card's
  own communication settings.
- Phase 9 integration work has started with a message delivery gateway.
  `NotificationLog` now acts as the message outbox/audit row with direction,
  provider and external message id fields. Appointment reminders route outbound
  email, Telegram and WhatsApp messages through `MessageDeliveryGateway`; the
  WhatsApp adapter is disabled until its system endpoint/token settings are
  configured.
- `AssistantActionProposal` is now the local LLM/MCP draft-and-review record.
  It stores source, action type, patient/appointment context, risk, payload and
  review status. High-risk or critical medical/financial actions force human
  approval and cannot be marked applied unless the previous status was
  `approved`.
- The first CRM-side MCP contract is exposed through narrow authenticated
  routes under `EspoDental/Integration`: tool discovery, bounded patient
  context read and assistant proposal creation. The contract deliberately does
  not expose direct payment, visit-finish, medical-note or invoice-cancel
  mutations.
- Phase 9 Proxmox migration planning is documented in
  `docs/proxmox-vm-migration.md`, including AOOSTAR WTR MAX topology, VM
  directory layout, Synology backup, VM restore, verification, ongoing backup
  retention and rollback.
- The local LLM virtual administrator design is documented in
  `docs/virtual-administrator-design.md`. It defines the assistant as a local
  operator aide that uses only the MCP/Integration contract, drafts messages,
  creates `AssistantActionProposal(source=llm)` records and refuses direct
  medical/financial mutations.
- Patient balance follows the product convention: positive value is patient
  prepayment/credit, negative value is patient debt. The patient balance field
  is color-coded in the UI: green for positive credit and red for debt.
- Invoice payment acceptance uses a modal form with amount input and a payment
  method dropdown instead of a free-text method prompt.
- Server-side payment acceptance rejects invoice-linked payments for draft,
  paid, storno or cancelled invoices, patient/clinic mismatches and amounts
  above the invoice balance. Unlinked inbound payments remain the documented
  prepayment/credit path.
- Cash-desk corrections now use an explicit correction workflow: a refund payment
  is created as a separate outbound `Payment` linked through
  `refundOf`, and the original posted payment remains immutable. The refund
  service caps cumulative refunds at the original payment amount. Invoice
  storno recalculates current payment state and blocks paid or partially-paid
  invoices with `Refund invoice payments before storno`, so staff must refund
  linked payments before cancelling the invoice debt.
- `DoctorShift` is introduced as the first schedule-availability model:
  regular/additional shifts open doctor availability, closed shifts block time,
  shifts can be scoped to a cabinet, and an optional assistant on the matching
  shift is written to the appointment automatically. `freeSlots` now respects
  active doctor shifts when a doctor is selected. Existing installs remain
  migration-safe: if a doctor has no active regular/additional shifts configured
  in the clinic, the previous global clinic work window is still used, while
  closed shifts can still block exceptions.
- Cabinet-level closures are represented by `DoctorShift(type=closed)` records
  scoped to a cabinet without a doctor. They remove matching free-slot
  suggestions and are enforced again during appointment save.
- `DoctorShiftTemplate` adds the recurring schedule helper. A template stores
  one or more weekdays, local start/end time, date range, type and optional
  assistant/cabinet pairing. The detail action `Generate Shifts` creates
  ordinary `DoctorShift` records in UTC, links them back to the template and
  skips already-generated matching shifts.
- The next-appointment loop now has contextual booking entry points from
  `Visit` and `Invoice` detail views in addition to the patient card action.
  They open the same short appointment modal, prelink the patient, and carry
  clinic, doctor, cabinet and visit note context where available. Invoice
  booking also loads the linked visit to recover doctor/cabinet context after
  payment.
- Patient detail now has a `Patient History` panel backed by
  `Patient/action/history`. The panel shows future appointment rows above past
  visit rows, using clinic-local times and linking directly to the appointment
  or visit records, so the receptionist can verify the next booking without
  digging through separate relationship panels.
- Patient detail now has a `Financials` panel backed by
  `Patient/action/financials`. It summarizes current balance, open invoice
  debt and unallocated credit, then shows open invoice rows and recent payment
  rows with direct links, so reception can see the patient's financial state
  without switching to separate invoice/payment lists.
- EspoDental admin settings expose editable module dictionaries for payment
  methods, tooth-chart condition options/colors and tooth-surface labels.
  These settings are admin-only via the EspoCRM admin settings page and are
  consumed by the payment modal and tooth-chart editor.
- The tooth chart renderer now stores and edits surface-level tooth state and
  renders adult/pediatric FDI charts with tooth silhouettes plus surface
  diagrams. Mixed dentition renders both adult and pediatric charts.
- Patient detail now has a `Clinical Files` panel backed by
  `Patient/action/files`. It shows recent `VisitPhoto` records with thumbnail,
  originating visit/date context and source links, plus completed
  `HealthQuestionnaire` PDF/signature download links. The standard patient
  `photos` and `healthQuestionnaires` relationship panels are read-oriented;
  photos should be added from visits and questionnaires should be issued
  through the QR/token action.
- Patient detail now has a `Questionnaire Summary` panel backed by
  `Patient/action/questionnaireSummary`. It shows the latest questionnaire
  date, schema version, language, alert/expired flags, generated PDF/signature
  files and localized answers grouped by `questionnaireSchema.json`, plus a
  compact list of recent questionnaires.
- Patient detail now has a `Care Summary` panel backed by
  `Patient/action/careSummary`. It shows family links from the optional linked
  parent patient, manual guardian fields and linked child patients, plus recent
  orthodontic cards with status, opening date, doctor, appliance and
  malocclusion context. The patient relationship layout also exposes
  `childPatients` and `orthodonticCards`; child links are read-oriented so
  parent assignment stays controlled by the child patient card.
- Patient detail now has a `Tooth Chart History` panel backed by
  `Patient/action/toothCharts`. It shows recent tooth-chart snapshots with
  recorded date, dentition type, linked visit, doctor and annotated-teeth
  count, giving the doctor a fast patient-level route into historical tooth
  chart records.
- Patient detail now has a `CBCT / Orthanc` panel backed by
  `Patient/action/cbctOrthanc`. It shows visit imaging records for X-ray,
  panoramic and CT/CBCT categories, plus any visit photos with Orthanc
  URL/Study UID. It also shows orthodontic imaging studies from linked
  orthodontic cards, including X-ray photo types, file links and Orthanc UID.
- `HealthQuestionnaire.items` now uses a schema-driven answer table backed by
  `HealthQuestionnaire/action/answers`. The table groups localized question
  labels from `questionnaireSchema.json`, renders Yes/No/text values and marks
  positive alert answers.
- Patient detail now shows a visible questionnaire warning banner when
  `questionnaireExpired` or `questionnaireHasAlerts` is true.
- `InvoicePdfBuilder` now uses the injectable EspoCRM
  `Espo\Core\Utils\Language` service; this fixes a `finishVisit` failure that
  appeared when `InvoiceService` was constructed.
- The questionnaire question schema is stored in
  `src/files/custom/Espo/Modules/EspoDental/Resources/metadata/dental/questionnaireSchema.json`.
  Existing completed questionnaires are not rewritten when the schema changes;
  the new schema affects newly issued forms and generated output.

Verification completed after this slice:

- `php rebuild.php` inside the local EspoCRM container passed.
- `php command.php update-app-timestamp` inside the local EspoCRM container
  passed.
- PHP syntax checks passed for all module PHP files inside the local EspoCRM
  container.
- JSON metadata parse passed for 351 JSON files.
- Browser smoke checks confirmed preliminary patient creation, hidden technical
  fields, required phone, default clinic assignment, questionnaire required
  answers and successful conversion with PDF/signature records.
- Browser smoke checks confirmed direct create buttons are hidden for `Visit`
  and `Invoice` after rebuild/reload.
- Browser smoke checks confirmed the EspoDental admin settings page renders the
  new dictionary editors, the payment modal shows configured payment methods,
  and visit detail still renders the configured tooth-chart legend.
- Direct API `POST /Visit` and `POST /Invoice` with valid linked records return
  `403` when attempted outside the module workflow.
- API smoke check created a `VisitServiceLine`, copied 3 service material
  norms into `VisitMaterialLine`, manually changed one actual quantity, then
  completed the visit. `finishVisit` returned invoice
  `INV-2026-00001`, created 6 stock movements with
  `sourceVisitMaterialLineId`, and set the visit to `finished`.
- API smoke checks after the clinical UX slice confirmed:
  `GET /Visit/action/toothChart` returns/creates a tooth chart for an existing
  visit; direct `PATCH Appointment.status=finished` returns `400`; direct
  `PATCH VisitServiceLine` for a finished visit returns `409`; in-progress
  service/material line saves recalculate price/cost from catalog records.
- Browser smoke after rebuild/cache clear confirmed the left menu shows only
  `Каталог услуг` and `Материалы`, the visit page hides appointment/status/
  stream/invoices, the tooth chart renders immediately, service lines show
  `Услуга / Кол-во / Скидка / Сумма`, and material lines show
  `Материал / Плановый расход / Фактический расход / Ед.`.
- Browser smoke on 2026-05-15 confirmed `ToothChartSnapshot` edit opens with a
  save action; clicking a tooth opens a surface editor; saving tooth 18 with
  occlusal caries persists as `teeth.18.surfaces.o.c = caries`. The temporary
  test mark was reset after verification.
- Browser smoke on 2026-05-15 confirmed the visit service line edit form shows
  category-first service selection and hides the generic service link field.
  Structural PHPUnit coverage now confirms that picker is an expandable service
  catalog tree rather than a two-select control.
- Browser smoke on 2026-05-22 confirmed the visit service-line create form
  renders the expanded service catalog tree with search, category toggles and
  service rows, with the old category/service select controls removed.
- Browser smoke on 2026-05-15 confirmed a mixed-dentition visit preview
  includes pediatric numbering such as `55` together with the adult chart.
- Browser smoke on 2026-05-22 confirmed the same behavior with a real child
  patient (`6a05deb4e6c69bd96`, birth date `2020-01-01`, age 6,
  `isChild=true`). The patient detail UI shows `Ребенок`, the tooth-chart
  history row is `Смешанный`, and the `ToothChartSnapshot` detail SVG contains
  both adult labels (`18`, `48`) and pediatric labels (`55`, `85`).
- API smoke confirmed `GET /Patient/action/files` returns recent visit photos
  with `visitName`, `recordedAt` and `imageId`, plus questionnaire `pdfFile`
  and `signatureAttachment` links for the selected patient.
- Browser smoke confirmed the patient detail `Clinical Files` panel renders in
  Russian with the visit photo thumbnail/context, Orthanc link and questionnaire
  PDF/signature links.
- API/browser smoke on 2026-05-15 confirmed existing patient debt recalculated
  from `+8050` to `-8000` after one recorded partial payment, and the payment
  dialog shows a dropdown with localized method labels.
- Structural PHPUnit coverage confirms `Visit` and `Invoice` expose
  `Book Next Appointment` actions, the handlers open the short contextual
  appointment modal, and RU / EN / ES labels are present.
- Structural PHPUnit coverage confirms the patient history endpoint returns
  future appointments before past visits, the patient detail view renders the
  history panel, and RU / EN / ES labels are present.
- Structural PHPUnit coverage confirms the patient financial endpoint
  summarizes open invoice balances, unallocated credit and recent payments,
  the patient detail view renders the financial panel, and RU / EN / ES labels
  are present.
- Structural PHPUnit coverage confirms role-specific dashboard templates are
  seeded for administrator, doctor, assistant, manager and stock workflows,
  and that doctor/assistant/stock dashboards do not expose finance widgets
  outside their ACL surface.
- Role-specific dashboard templates are assigned by bootstrap to active regular
  users who have an EspoDental role or matching EspoDental role-team and no
  existing `dashboardTemplate` choice: manager, administrator, doctor,
  assistant and stock users receive the matching focused workspace while
  manually selected user templates are preserved.
- API/bootstrap smoke on 2026-05-22 confirmed temporary administrator, doctor,
  assistant, manager and stock users received the matching dashboard templates,
  and a team-only doctor user received the doctor dashboard through the
  EspoDental Doctors team fallback. The temporary smoke users were deleted
  after verification.
- Phase 10 management reporting has started with a doctor productivity report.
  `GET /EspoDental/Report/doctorProductivity` returns finished-visit counts,
  service-line counts, gross finished-visit amount and average amount per visit
  by doctor for a bounded period. The manager dashboard template includes the
  `DoctorProductivity` dashlet, and bootstrap now updates existing dashboard
  templates when the seeded layout/options change.
- Phase 10 now includes a cabinet utilization report.
  `GET /EspoDental/Report/cabinetUtilization` returns active cabinet rows with
  appointment count, finished count, occupied minutes, available minutes and
  utilization percent for the selected period and working-hour window. The
  manager dashboard template includes the `CabinetUtilization` dashlet.
- Browser smoke on 2026-05-15 confirmed `Appointment.dateStart` renders a
  free-slot picker in edit mode, loads slots for the selected doctor/cabinet,
  respects the EspoCRM timezone for 08:00-21:00 display, and writes the chosen
  slot back into the hidden native date/time inputs.
- Browser smoke on 2026-05-15 confirmed the appointment create form no longer
  shows the "Free slots" button, manual "Start" field or assistant picker, and
  shows `Основная клиника` as the default clinic.
- Browser smoke on 2026-05-15 confirmed the patient header button
  `Записать на прием` opens the short appointment modal from the patient card;
  the modal hides `Расширенная форма`, assistant, manual start, assigned user
  and teams, and prefills `Основная клиника`.
- API smoke on 2026-05-15 confirmed free-slot search no longer returns a
  selected doctor/cabinet slot when the doctor is already booked in another
  cabinet at the same time.
- Browser smoke on 2026-05-22 confirmed the patient detail `Questionnaire
  Summary`, `Care Summary` and `Tooth Chart History` panels render in Russian
  after rebuild/app-timestamp refresh. The questionnaire panel shows localized
  grouped answers, date/version and generated PDF/signature links.
- Browser smoke on 2026-05-22 confirmed the patient detail `CBCT / Orthanc`
  panel renders in Russian after rebuild/app-timestamp refresh and is placed
  before `Clinical Files`.
- API smoke on 2026-05-15 confirmed manual `DELETE /PreliminaryPatient/{id}`
  returns `403` with reason `Preliminary patients cannot be manually removed`.
  The temporary smoke record was then marked deleted directly in the local
  development database.
- API smoke on 2026-05-22 confirmed direct `PATCH Material.currentStock`
  returns `409` and direct `PATCH StockMovement.quantity` on a posted movement
  returns `409`.
- Structural PHPUnit coverage confirms posted payments are immutable, refund
  payment corrections do not mutate the source payment, cumulative refunds are
  capped and invoice storno requires linked invoice payments to be refunded
  first.
- Structural PHPUnit coverage confirms the patient `Care Summary` endpoint and
  panel expose family links and orthodontic cards, and that patient
  relationship panels include child patients and orthodontic cards.
- Structural PHPUnit coverage confirms the patient `Tooth Chart History`
  endpoint and panel expose recent tooth-chart snapshots, source visits and
  annotated-teeth counts.
- Structural PHPUnit coverage confirms the patient `Questionnaire Summary`
  endpoint and panel expose latest questionnaire answers grouped by schema,
  generated files, recent questionnaires and alert/expired flags.
- Structural PHPUnit coverage confirms the patient `CBCT / Orthanc` endpoint
  and panel expose visit and orthodontic imaging studies, source records,
  file links and Orthanc URL/UID context.
- Structural PHPUnit coverage confirms the visit material relationship panel
  uses the inline quantity editor, limits it to active visits with edit ACL and
  saves actual consumption through a PATCH request.
- Browser/API smoke on 2026-05-22 confirmed an active visit material panel
  renders inline quantity inputs, saves an actual-consumption change through
  the UI/API, and a finished visit renders the same panel read-only with no
  inline editor. The temporary material quantity change was restored.
- API smoke on 2026-05-22 confirmed the explicit cash-desk correction workflow
  on the local stack: draft invoice payment is rejected, over-balance payment
  is rejected, issuing an invoice allows payment, storno is blocked with
  `Refund invoice payments before storno` while net paid amount is positive,
  refunds are separate outbound `Payment` records linked via `refundOf`,
  cumulative over-refund is rejected, and storno succeeds after full refund.
- API/browser smoke on 2026-05-22 confirmed the schedule-management slice on a
  real clinic week: a `DoctorShiftTemplate` with Monday and Wednesday selected
  generated two linked UTC `DoctorShift` rows; a cabinet-only closed shift
  removed overlapping Monday slots from `freeSlots`; direct appointment save
  inside the cabinet closure returned `409 Cabinet is closed for this time.`;
  and the template detail UI rendered `Дни недели: Понедельник, Среда`.
- API/browser smoke on 2026-05-22 confirmed the full
  `payment -> next appointment` receptionist loop on the local stack:
  `INV-2026-00003` was issued and paid with `PMT-2026-00006`, a free
  clinic-local slot was selected for the same patient/doctor/cabinet context,
  `Appointment 6a1051ecc7f620b1c` was created for `2026-06-04 08:00`
  Europe/Moscow, `Patient/action/history` returned the future appointment above
  the past visit, `Patient/action/financials` showed balance/open debt/credit
  at zero, and the patient detail UI rendered the future appointment plus the
  completed payment row.

## 6. Known Gaps Against Product Spec

The following requirements still need implementation or explicit verification:

- WhatsApp needs provider-specific browser or API acceptance once clinic
  credentials exist.

## 7. Development Rule

Future work should be implemented as connected vertical slices. Do not add
isolated fields or screens unless they participate in the accepted patient flow
or an explicitly planned later slice.
