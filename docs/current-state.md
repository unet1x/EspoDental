# EspoDental Current State

Last updated: 2026-05-15

This file is the handoff document for future development sessions. It describes
what has been verified, what exists in metadata, and what still needs product
acceptance.

## 0. Regression Handoff Added 2026-05-21

A focused regression handoff was added after reviewing the latest front-desk
and booking changes:

- `docs/regression-handoff-2026-05-21.md`

Read it before starting new fixes. It records likely regressions in the
resource-calendar timezone contract, global appointment quick-create,
`finishVisit` atomicity/idempotency, server-side payment guards and appointment
final-status naming.

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
installed there.

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
- `Finish Visit` button visibility is tied to `Visit.status = in_progress`;
  completed visits no longer show a misleading finish action after metadata
  refresh.
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
- `VisitServiceLine` edit forms add a category-first service picker above the
  hidden generic service link field. Selecting a service still writes the
  normal `serviceId/serviceName` link and reuses existing price/material logic.
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
- Patient balance follows the product convention: positive value is patient
  prepayment/credit, negative value is patient debt. The patient balance field
  is color-coded in the UI: green for positive credit and red for debt.
- Invoice payment acceptance uses a modal form with amount input and a payment
  method dropdown instead of a free-text method prompt.
- EspoDental admin settings expose editable module dictionaries for payment
  methods, tooth-chart condition options/colors and tooth-surface labels.
  These settings are admin-only via the EspoCRM admin settings page and are
  consumed by the payment modal and tooth-chart editor.
- The tooth chart renderer now stores and edits surface-level tooth state and
  renders adult/pediatric FDI charts with tooth silhouettes plus surface
  diagrams. Mixed dentition renders both adult and pediatric charts.
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
- JSON metadata parse passed for 331 JSON files.
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
- Browser smoke on 2026-05-15 confirmed a mixed-dentition visit preview
  includes pediatric numbering such as `55` together with the adult chart.
- API/browser smoke on 2026-05-15 confirmed existing patient debt recalculated
  from `+8050` to `-8000` after one recorded partial payment, and the payment
  dialog shows a dropdown with localized method labels.
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
- API smoke on 2026-05-15 confirmed manual `DELETE /PreliminaryPatient/{id}`
  returns `403` with reason `Preliminary patients cannot be manually removed`.
  The temporary smoke record was then marked deleted directly in the local
  development database.

## 6. Known Gaps Against Product Spec

The following requirements still need implementation or explicit verification:

- harden atomic finish visit action around prepared material lines, stock
  movements, invoice creation and patient balance update;
- refine the visit service picker into a richer expandable category tree if the
  current two-select category-first picker is not ergonomic enough after user
  testing;
- add inline quantity editing in visit material relationship panels where
  EspoCRM relationship panels support it;
- make stock balance movement-based and correction-safe;
- make invoice/payment correction workflows explicit;
- polish patient-side health questionnaire tab and generated file visibility;
- show questionnaire expiry alert after 1 year;
- finish patient-card photo visibility with visit/date context;
- polish patient tabs: tooth chart, history, questionnaire, files, financials,
  orthodontics, family, CBCT;
- implement doctor/assistant working shifts: appointment free slots currently
  use the global clinic day window plus existing busy appointments, not
  personal doctor shifts. Target behavior is to show slots only inside
  approved doctor shifts, with "additional shift" records opening extra
  availability and assistant assignment coming from the shift pairing;
- verify pediatric/adult mixed chart behavior with a real child patient in the
  browser after the next patient-flow test run;
- complete role-based workspaces for administrator, doctor, assistant and
  manager;
- define and implement MCP/LLM/WhatsApp integration layer.

## 7. Development Rule

Future work should be implemented as connected vertical slices. Do not add
isolated fields or screens unless they participate in the accepted patient flow
or an explicitly planned later slice.
