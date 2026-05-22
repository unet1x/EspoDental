# EspoDental — Release Notes / История релизов

> Latest first. Versions follow SemVer with leading `0.` until 1.0.

## Unreleased — Front desk intake hardening

- Direct normal-user `Patient` creation is blocked; patients are created from
  `PreliminaryPatient` conversion or explicit migration/admin pathways.
- Reception `PreliminaryPatient` layout is simplified: technical conversion
  fields, assigned user, teams and questionnaire-token links are hidden.
- `PreliminaryPatient.phone` is required; `status` defaults to `entered`.
- EspoDental settings include default clinic selection. Preliminary patients
  auto-fill clinic from that setting, or from the only active clinic.
- Conversion now centers on the health questionnaire flow: the receptionist
  starts conversion, the patient completes the QR/tablet form, and successful
  submission creates the patient.
- Converted preliminary patients are hidden from operational lists while the
  patient retains the conversion source link for reporting.
- Health questionnaire yes/no items are required, signature is required, and
  generated PDFs use a compact two-column answer layout.
- Direct visit and invoice creation are hidden in the UI and guarded by hooks;
  visits must be started from appointments, invoices must be created from
  visits.
- New `VisitMaterialLine` entity stores prepared material consumption copied
  from service norms. Stock write-off now uses these editable visit material
  lines when present and records `sourceVisitMaterialLine` on movements.
- Catalog navigation now exposes only `Каталог услуг` and `Материалы` in the
  normal menu; services/materials are reached through category records.
- Appointment quick booking is simplified around doctor/cabinet/start/duration,
  hides source/end/ownership noise, defaults clinic, adds confirmation, and
  blocks manual selection of visit-owned statuses.
- Appointment start time now has a free-slot picker backed by
  `EspoDental/Calendar/freeSlots`, so reception can choose an available time
  after doctor, cabinet and duration are set.
- The appointment create/edit form now shows a date plus free-time dropdown,
  no separate "Free slots" button, no native start datetime fields, no manual
  assistant selector, and the clinic link is prefilled from the module default.
- Patient detail now has a primary `Записать на прием` action that opens the
  short appointment modal with the patient prelinked and no "Full Form" button.
- The patient appointment relationship panel create/select plus flow is hidden;
  booking is routed through the explicit header action.
- Appointment free-slot labels now use the live selected duration instead of
  always showing 30-minute intervals.
- Free-slot labels and the patient booking success notification now use the
  clinic-local time, while the saved appointment keeps UTC `dateStart/dateEnd`.
- Appointment display names are now derived from clinic-local time instead of
  raw UTC storage values.
- Resource calendar appointment payloads now include clinic-local start/end
  values and timezone; drag/drop and resize send clinic-local values back to
  the server, which persists UTC `dateStart/dateEnd`.
- Free-slot search now matches the server conflict rules for doctor and patient
  occupancy: a doctor already booked in another cabinet no longer appears as
  available for the same time.
- Global `Appointment` quick-create is removed from the default workspace; the
  normal booking path is the contextual patient/preliminary-patient action.
- Added `DoctorShift` for the first schedule-availability slice: regular and
  additional shifts open doctor availability, closed shifts block time,
  cabinet-scoped shifts restrict matching cabinets, and the shift assistant is
  copied to the appointment automatically.
- `EspoDental/Calendar/freeSlots` now respects configured doctor shifts when a
  doctor is selected. Doctors with no active regular/additional shifts
  still fall back to the existing clinic work window for migration safety.
- `DoctorShiftTemplate` now supports multiple weekdays in one template; the
  `Generate Shifts` detail action creates one linked UTC `DoctorShift` per
  selected weekday/date match and remains idempotent.
- Cabinet-only closed shifts now block matching free-slot suggestions and are
  enforced again when an appointment is saved.
- Visit and invoice detail views now expose `Book Next Appointment`, opening
  the same short contextual appointment modal as the patient card and carrying
  patient, clinic, doctor, cabinet and visit-note context where available.
- Local API/browser acceptance on 2026-05-22 confirmed the full
  `payment -> next appointment` loop: a paid invoice, zero patient debt/credit,
  and a future appointment shown above the completed visit in patient history.
- Patient detail now includes a `Patient History` panel that lists future
  appointments above past visits with clinic-local times and direct links.
- Patient detail now includes a `Financials` panel with current balance, open
  invoice balance, unallocated credit, open invoices and recent payments.
- Payment corrections now create a separate outbound refund payment linked to
  the source payment instead of mutating the original posted row. Cumulative
  refunds are capped at the original amount, posted payments cannot be edited
  or removed through normal saves, and invoice storno now returns
  `Refund invoice payments before storno` until linked payments are refunded.
- Local API acceptance on 2026-05-22 confirmed draft/over-balance payment
  guards, refund-linked outbound payments, cumulative refund caps and storno
  after full refund on the running EspoCRM stack.
- Service-line price/currency/VAT always comes from the selected service;
  material-line unit/cost always comes from the selected material.
- Visit material panels now expose an inline material quantity editor for
  active visits, saving only actual consumption while keeping finished visits
  protected by the server-side guard.
- Visit service-line edit forms now use a searchable category tree instead of
  a two-select category/service picker.
- Doctor-facing visit layouts hide service price/currency/VAT, material cost,
  appointment/status, stream and invoice panels.
- Visit start now creates a tooth-chart snapshot; visit detail renders the
  tooth chart immediately with an edit link while the visit is in progress.
- Patient detail now includes a `Clinical Files` panel with recent visit photos
  linked to their visit/date context and health-questionnaire PDF/signature
  links. Patient-side photo and questionnaire relationship panels are
  read-oriented so records stay attached to their clinical source workflows.
- Patient detail now includes a `Questionnaire Summary` panel with latest questionnaire answers grouped by schema,
  date/version, alert and expired flags, plus generated PDF/signature links.
- Patient detail now includes a `Care Summary` panel with family links,
  manual guardian data, linked child patients and orthodontic cards. The
  standard patient relationship layout also exposes `childPatients` and
  `orthodonticCards`, with child links kept read-oriented.
- Patient detail now includes a `Tooth Chart History` panel with recent tooth-chart snapshots,
  source visit links, dentition type, doctor and annotated-teeth count.
- Local browser acceptance on 2026-05-22 confirmed a real child patient renders
  mixed dentition with both adult and pediatric tooth labels in the tooth-chart
  snapshot UI.
- Patient detail now includes a `CBCT / Orthanc` panel with visit and orthodontic imaging studies,
  source record links, file links and Orthanc URL/UID context.
- Health questionnaire detail now renders answers as localized, schema-driven
  grouped rows instead of raw JSON, and flags alert answers in the table.
- Patient detail now shows a visible warning banner when the questionnaire is
  expired or contains medical alert answers.
- Phase 9 integration work now has a message delivery gateway: appointment
  reminders route email, Telegram and WhatsApp through one adapter boundary,
  `NotificationLog` records direction/provider/external message id as the
  outbox audit row, and the WhatsApp adapter is configurable but disabled until
  endpoint/token settings are provided.
- WhatsApp reminder delivery now has provider-specific payload contracts:
  `generic` keeps the audited proxy payload, while `whatsapp-cloud`/`meta-cloud`
  builds the Meta Cloud API text-message payload for a full Graph
  `/{phone-number-id}/messages` endpoint.
- Added `AssistantActionProposal` as the LLM/MCP draft-and-review workflow.
  High-risk and critical medical/financial proposals require approval and are
  blocked from `applied` status unless already approved.
- Added the first CRM-side MCP contract under `EspoDental/Integration`: tools
  discovery, bounded patient context read and proposal creation, with no direct
  clinical or financial mutation endpoints.
- Added `docs/proxmox-vm-migration.md` with the AOOSTAR WTR MAX / Proxmox VM
  topology, Synology backup, VM restore, verification, ongoing backup retention
  and rollback procedure.
- Added `docs/virtual-administrator-design.md` for the local LLM assistant:
  local deployment boundary, allowed MCP tools, blocked direct mutations,
  approval workflow, prompt contract and acceptance criteria.
- EspoDental admin settings now include editable dictionaries for payment
  methods, tooth-chart conditions/colors and tooth-surface labels.
- Role workspace templates are now seeded for administrator, doctor,
  assistant, manager and stock workflows. They keep the shared clinic dashboard
  as the default while giving each role a focused home screen with only
  relevant dashlets.
- Bootstrap now assigns role-specific dashboard templates to active regular
  users with EspoDental roles or matching role teams when the user has no
  existing dashboard template selection, so the focused workspaces are applied
  automatically after staff assignment.
- Doctor productivity report is available at
  `EspoDental/Report/doctorProductivity` and as a manager dashboard dashlet,
  summarizing finished visits, service lines, gross amount and average visit
  amount by doctor for the current period.
- Cabinet utilization report is available at
  `EspoDental/Report/cabinetUtilization` and as a manager dashboard dashlet,
  summarizing active-cabinet appointment count, occupied hours, available
  hours and utilization percent for the selected period.
- No-show and cancellation report is available at
  `EspoDental/Report/noShowCancellations` and as a manager dashboard dashlet,
  summarizing appointment totals, no-shows, cancellations and issue-rate
  percentages by doctor for the selected period.
- Inventory status report is available at
  `EspoDental/Report/inventoryStatus` and as manager/stock dashboard dashlets,
  summarizing active-material stock level, current stock, period movement
  totals and calculated inventory value.
- Payroll calculation hardening: salary build now passes entered
  `hoursWorked` into hourly base calculation before the entry is saved, and
  doctor/assistant revenue percentages use the actual `VisitServiceLine.amount`
  field.
- Visit photos get quick-add defaults for name, patient and recorded date.
- Finished visits reject service/material line edits/removals with a server
  conflict.
- Material stock balance is now movement-based: `currentStock` and
  `stockLevel` are guarded as derived fields, posted `StockMovement` records are
  immutable in normal flows, and corrections are made by creating new movement
  records.
- Finishing a visit now runs invoice and stock work inside an EspoCRM database
  transaction before final visit and appointment statuses, and repeated finish
  calls reuse idempotent downstream records instead of creating duplicates.
- Bootstrap backfills old local/demo names for visit service/material lines,
  `patient — date` visits and tooth-chart snapshots.
- `Finish Visit` is now hidden for visits that are no longer in progress.
- Visit detail layout is simplified for doctors by removing assigned-user and
  team fields from the main form.
- Fixed invoice PDF builder dependency injection by using
  `Espo\Core\Utils\Language`.
- Role bootstrap now patches missing ACL scope rows into existing EspoDental
  roles, so new module scopes become available after bootstrap without
  recreating roles.
- Documentation now records the strict patient flow, future invariants,
  questionnaire schema location and acceptance checks for the next phases.
- The 2026-05-21 regression handoff is covered by
  `RegressionHandoff20260521Test`.

## 0.16.0 — Staging stack + nightly backup/restore pipeline

- New compose stack `deploy/staging/docker-compose.yml` brings up a second
  EspoCRM instance on the same Synology (ports 8090/8091, isolated network
  and volumes, prominent "STAGING" banner via
  `ESPOCRM_CONFIG_NOTIFICATIONS_FOOTER`).
- New scripts in `deploy/scripts/`:
  - `lib/common.sh` — logging with pipeline-id, `load_env`, `require_env`.
  - `lib/alert.sh` — `alert_telegram` (Bot API) + `alert_email`
    (curl-SMTP, no extra packages on DSM).
  - `backup-prod.sh` — `mariadb-dump --single-transaction --quick --routines
    --triggers --events` of prod, gzip, optional `tar` of uploads, retention
    pruning (default 14 days), `db-latest.sql.gz` symlink.
  - `restore-to-staging.sh` — stop staging web tier → drop/recreate DB →
    `gunzip | mariadb` → `rsync` uploads → restart → `rebuild.php` →
    sanity check (HTTP 200 + `SELECT COUNT(*) FROM patient` prod vs staging).
  - `nightly.sh` — orchestrator. Retries the backup once on failure. Sends
    differentiated Telegram + email alerts depending on which step broke
    (`Backup FAILED twice`, `Restore to staging FAILED`,
    `Staging sanity check FAILED`). Writes a single log per run keyed by
    `pipeline_id`.
- `deploy/.env.example` extended with `ALERT_TELEGRAM_BOT_TOKEN`,
  `ALERT_TELEGRAM_CHAT_ID`, `ALERT_EMAIL_TO/FROM`, `ALERT_SMTP_URL`,
  `STAGING_*`, `LOG_DIR`, `PATIENT_TABLE_NAME`.
- `docs/admin-guide.md` — new section 9 "Staging environment + nightly
  pipeline" with directory layout, first-time setup, cron schedule,
  promotion workflow (`git pull` only — no automation across hosts),
  rollback recipe.
- `Phase16MetadataTest`: file presence + executable bit + structural
  content checks for compose, scripts, alerts and orchestrator.
- **Tests:** ~186 / ~2010 assertions.

## 0.15.0 — Console seeder + GitHub Release workflow

- Refactored `AfterInstall.php` — extracted seeding logic into
  `Tools/Installer/RoleSeeder.php` (idempotent service used by both
  Extensions UI installer and CLI).
- New console command `espo-dental-seed-roles`
  (`Tools/Console/SeedRolesCommand.php`) registered via
  `Resources/metadata/app/consoleCommands.json`. Lets you seed teams,
  roles and starter service categories on Docker volume-mount installs
  without having to upload a zip.
- Updated `README.md` and `docs/admin-guide.md`: Docker section now
  reflects the volume-mount flow, links to Releases, mentions the new
  CLI command.
- Added `.github/workflows/release.yml`: every push of a tag `v*.*.*`
  builds the extension zip and publishes a GitHub Release with the
  artifact attached.
- `Phase15MetadataTest`: 6 assertions.
- **Tests:** ~180 / ~1980 assertions.

## 0.14.0 — Polish: docs + Docker smoke

- Added `README.md` (EN + RU dual sections, install, roles, features).
- Added `docs/admin-guide.md`, `docs/user-guide.md`, `docs/release-notes.md`.
- Added `deploy/smoke/` — Docker Compose smoke test that boots MariaDB +
  EspoCRM + the module sources mounted via volume, plus
  `deploy/smoke/smoke.sh` that waits for HTTP 200 on the installer page.
- `Phase14MetadataTest`: 5 docs/smoke assertions.
- **Tests:** 173 / 1924 assertions.

## 0.13.0 — Resource Calendar enhancements

- Week view (7 days × N cabinets in a single grid).
- Drag-to-resize duration via the bottom edge of an appointment card.
- Cabinet / clinic filters at dashlet level and API.
- `GET /EspoDental/Calendar/freeSlots` — finds free slots across cabinets
  (and optionally doctor) in working hours.
- "Find slot" button in the Resource Calendar toolbar.
- `CalendarService.getDayData` accepts `cabinetId`.
- Locale extensions for calendar UI strings.
- **Tests:** 168 / 1916 assertions.

## 0.12.0 — Orthodontic module

- New entities: `OrthodonticCard`, `TreatmentStage`, `ToothMovementPlan`,
  `OrthoPhoto`, `CephalometricMeasurement`.
- `OrthodonticCard` auto-number `ORTHO-YYYY-NNNNN` via `AssignNumber` hook.
- Status workflow: open → in_treatment → retention → completed (or cancelled).
- `closeCard` / `reopenCard` actions with final-status validation.
- `ToothMovementPlan` with FDI tooth number + 8 movement axes.
- `OrthoPhoto` with 14 photo types × 4 phases + optional Orthanc UID.
- `CephalometricMeasurement` with 16 codes + reference normal ranges.
- `ActiveOrthoCases` dashlet, `ActiveCards` / `MyDoctor` bool filters.
- Full RU / EN / ES locales, ACL across all 5 roles.
- **Tests:** 161 / 1891 assertions.

## 0.11.0 — Resource Calendar dashlet

- Custom pure-JS `resource-grid.js` (~190 lines, no external deps).
- HTML5 drag-and-drop to move appointments.
- Click empty cell → opens "Create appointment" prefilled with cabinet/time.
- `CalendarService.getDayData`, `moveAppointment`.
- Configurable hours, row granularity (10 / 15 / 30 / 60 min).
- **Tests:** 145 / 1667 assertions.

## 0.10.0 — Salary module

- `SalaryProfile`, `SalaryEntry`, `SalaryBonus` entities.
- 4 rate types: `fixed`, `per_visit`, `percent_revenue`, `mixed`.
- `SalaryCalculator` aggregates doctor / assistant revenue + bonuses.
- `SalaryService.buildEntry`, `approveEntry`, `payEntry` (creates
  `Payment(direction=out)`), `cancelEntry`.
- `PayrollThisMonth` dashlet.
- ACL: Manager + Stock Manager full; Doctor / Assistant / Administrator read-own.
- **Tests:** 136 / 1635 assertions.

## 0.9.0 — Reports & dashlets

- `DateRangeHelper`, 18 bool-filter classes across 7 entities.
- `ReportService`: `getMonthlyRevenue`, `getInvoiceSummary`, `getLowStockSummary`.
- 5 dashlets: Today's Appointments, Open Invoices, Low-Stock Materials,
  Recent Visits, Monthly Revenue (with custom SVG bar chart).
- **Tests:** 122 / 1465 assertions.

## 0.8.0 — Notification log + Telegram

- `NotificationLog` entity (channel, status, recipient, payload).
- Settings extensions for reminder template and channel toggles.
- `Patient.telegramChatId`.
- ACL on `NotificationLog` for 5 roles.
- **Tests:** 113 / 1290 assertions.

## 0.7.0 — Inventory

- `Material`, `MaterialCategory`, `StockMovement`, `ServiceMaterial`,
  `LowStockAlert`.
- `CheckStockThresholds` cron job.
- Locale extensions and per-entity files for stock entities.
- **Tests:** 100 / 1194 assertions.

## 0.6.0 — Reminders & Telegram bot scaffolding

- `TelegramSender` (HTTP API client).
- `ReminderTemplate`, `ReminderService`.
- `SendAppointmentReminders` job.

## 0.5.0 — Invoices, payments, cashflow

- `Invoice` / `InvoiceLine`, `Payment`, `Service` / `ServiceCategory`.
- Invoice status workflow (`draft` → `unpaid` → `partially_paid` → `paid`).

## 0.4.0 — Visit & tooth chart

- `Visit`, `VisitServiceLine`, `VisitPhoto`, `ToothChartSnapshot`.

## 0.3.0 — Anonymous questionnaire flow

- `PreliminaryPatient`, `QuestionnaireToken`, `HealthQuestionnaire`.
- Anonymous self-fill URL.

## 0.2.0 — Scheduling MVP

- `Appointment`, `AppointmentStatusLog`, `Cabinet`, `Clinic`.
- `CheckConflicts` hook prevents overlapping bookings.

## 0.1.0 — Patient core

- `Patient`, address, contact details, link to clinic.
- RU / EN / ES baseline locales.

## 0.0.x — Infrastructure

- Module skeleton, manifest, build script, PHPUnit + PHPCS + PHPStan,
  Docker Compose stack for Synology DSM.
