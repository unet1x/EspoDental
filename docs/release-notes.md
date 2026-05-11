# EspoDental — Release Notes / История релизов

> Latest first. Versions follow SemVer with leading `0.` until 1.0.

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
