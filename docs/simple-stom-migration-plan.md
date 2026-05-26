# SimpleStom Migration Plan

Last updated: 2026-05-26

This document tracks the read-only analysis of `/Users/unet1x/Codex/SimpleStom`
and the step-by-step plan for moving its product behavior and visual language
into the EspoDental EspoCRM module.

## Source Boundary

SimpleStom is a reference source only. Do not modify files under
`/Users/unet1x/Codex/SimpleStom` during this migration.

Reference materials reviewed:

- `docs/README.md`
- `docs/01-product-brief.md`
- `docs/02-functional-spec.md`
- `docs/03-user-flows.md`
- `docs/04-ui-map.md`
- `docs/05-data-model.md`
- `docs/06-roadmap.md`
- `docs/07-decisions.md`
- `docs/08-screen-specs.md`
- `docs/09-development-plan.md`
- `docs/10-questionnaire-spec.md`
- `docs/11-implementation-log.md`
- `docs/12-redesign-delivery-plan.md`
- `docs/13-demo-runbook.md`
- `docs/14-customer-feedback-layout-plan.md`
- `docs/15-customer-feedback-renders.md`
- `mockups/01-dashboard.png` through `mockups/17-feedback-patients.png`
- `frontend/src/**`
- `backend/app/**`
- `demo_seed.py`
- `test_api.py`

Important visual reference order:

1. Use the latest feedback renders as the target for calendar, cash desk,
   dashboard, inventory and patients:
   `13-feedback-calendar.png`, `14-feedback-cashdesk.png`,
   `15-feedback-dashboard.png`, `16-feedback-inventory.png`,
   `17-feedback-patients.png`.
2. Use the earlier operational mockups `01-dashboard.png` through
   `12-payroll.png` for screens not superseded by feedback.
3. Transfer the SimpleStom look as an EspoCRM module experience, not as a
   separate FastAPI/React application.

## Migration Rules

- One stage must end with implementation, verification, documentation update
  and a separate commit.
- Existing EspoDental behavior is preserved unless the stage explicitly
  replaces it.
- Existing uncommitted work in the repository must not be reverted or included
  in stage commits unless it is part of the stage.
- EspoDental already owns the runtime: PHP 8.2+, EspoCRM 9.2+, metadata,
  controllers, services, dashlets, client AMD views and bootstrap seeders.
- SimpleStom concepts should be mapped to EspoDental entities first. Add a new
  entity only when no safe EspoCRM-native equivalent exists.
- For UI work, prefer isolated custom client views and module CSS over broad
  global EspoCRM skin changes.
- For data changes, keep the current module pattern: metadata entityDefs,
  PHP entities/controllers/services/hooks, installer seeding and tests.

## Product Mapping

| SimpleStom concept | EspoDental target | Migration note |
| --- | --- | --- |
| Clinic | `Clinic` | Exists. Compare timezone, currency, language and schedule settings. |
| Cabinet | `Cabinet` | Exists. Capability model is less explicit than SimpleStom. |
| CabinetCapability | New or encoded settings | Needed for slot filtering by procedure requirements. |
| StaffProfile | `SalaryProfile`, `User`, roles/teams | EspoDental has salary profiles and user roles; assistant/doctor schedulability needs a gap check. |
| Group | EspoCRM `Team` / role assignment | Prefer native teams unless product requires separate group semantics. |
| PrePatient | `PreliminaryPatient` | Exists and drives front-desk intake. |
| Patient | `Patient` | Exists with questionnaire, balance, child/guardian and clinical links. |
| PatientFlagDefinition | New or patient status/tag fields | Needed if SimpleStom-style custom searchable flags are required. |
| FamilyLink | Existing parent/child links or new relation entity | Current model has parent/child links; broader family graph needs a gap check. |
| Appointment | `Appointment` | Exists. Status names differ and need a compatibility decision. |
| AppointmentPlannedService | `Appointment` fields / new relation | EspoDental has service/catalog data but planned services need explicit mapping. |
| AppointmentRescheduleRequest | New entity or `AssistantActionProposal` | Patient portal reschedule workflow is not equivalent to direct move. |
| AppointmentSlotHold | New entity/service state | Needed for patient portal reschedule holds. |
| AppointmentWaitlistEntry | New entity | Required by the final calendar feedback plan. |
| PatientPortalSession/Event | New public-access model or token extension | Current module has questionnaire tokens, not a full patient portal. |
| Task | New entity or Espo task equivalent | Needed for dashboard "my tasks" and operational handoffs. |
| Reception | `Visit` | Existing clinical workflow maps to Visit. |
| ReceptionService | `VisitServiceLine` | Exists. |
| ReceptionMaterialUsage | `VisitMaterialLine` | Exists for planned/actual material consumption. |
| DentalChartSnapshot/Event | `ToothChartSnapshot` | Existing renderer and snapshot model are the target. |
| PatientFile | `Attachment`, `VisitPhoto`, questionnaire PDF links | Need a unified patient file UX without duplicating storage. |
| ServiceCategory/Service | `ServiceCategory`, `Service` | Exists. Need UI and color/requirements comparison. |
| InventoryWarehouse/StockLot | New warehouse/lot layer over `Material`/`StockMovement` | Required for final inventory feedback and FEFO expiry tracking. |
| InventoryTransaction | `StockMovement` | Exists but warehouse/lot dimensions need extension. |
| Invoice/InvoiceLine | `Invoice`, `InvoiceLine` | Exists. |
| Payment/FinancialAdjustment | `Payment` plus service flows | Exists partly; advance, cash shift and correction semantics need comparison. |
| FinancialDocumentSequence | Existing invoice numbering hooks | Exists for invoices; act/receipt numbering needs gap check. |
| ReportDefinition | New report-template layer or seeded dashlets | EspoDental has dashlets and report endpoints; SimpleStom builder is broader. |
| PayrollRun/Line/Adjustment | `SalaryEntry`, `SalaryBonus`, `SalaryProfile` | Existing salary module differs from SimpleStom payroll runs. |
| IntegrationSettings/Secret | Existing settings + integration services | Telegram exists; WhatsApp/provider secret UI needs gap check. |

## Stage Plan

### Stage 0 - Migration Contract

Goal: make the transfer plan explicit inside EspoDental.

Work:

- Add this document.
- Link it from the main documentation index.
- Record that SimpleStom was read-only during analysis.

Verification:

- `git diff --check`
- Documentation diff review.

### Stage 1 - Gap Matrix And Acceptance Scope

Goal: turn the high-level mapping into an actionable implementation matrix.

Work:

- Compare SimpleStom entities, endpoints, screens and demo flows against
  EspoDental metadata, services, client views and tests.
- Mark each item as `exists`, `extend`, `new`, `defer` or `do-not-port`.
- Define the minimum acceptance demo for every remaining stage.

Verification:

- Add or update structural tests that lock the chosen scope where possible.
- `vendor/bin/phpunit tests --no-coverage` if dependencies are present.

### Stage 2 - Visual System Foundation

Goal: introduce the SimpleStom visual language safely inside EspoDental.

Work:

- Create reusable client helpers/styles for the light CRM surfaces:
  soft green background, white operational panels, `#438f7e` primary action,
  compact badges, split panels and dense tables.
- Apply them first to new or redesigned EspoDental custom views, not to all of
  EspoCRM globally.
- Document visual tokens and component rules.

Verification:

- JS syntax smoke for changed client files.
- Metadata integrity tests.

### Stage 3 - Dashboard Actions And Tasks

Goal: replace calendar-like dashboard thinking with the feedback dashboard
model.

Work:

- Add an operational task model if native EspoCRM tasks are not suitable.
- Add dashboard data endpoints/service methods for waiting patients,
  reschedule actions, assigned tasks, created tasks, alerts and weekly work.
- Redesign role dashboard templates for administrator, doctor, assistant,
  manager and stock manager.

Verification:

- Service tests for task status transitions and dashboard aggregates.
- Role/template metadata tests.

### Stage 4 - Calendar Feedback UX

Goal: make the calendar match the final feedback model.

Work:

- Redesign the resource calendar dashlet/client view with compact toolbar,
  mini-calendar, right-side waiting/cancelled panel and slot-first booking.
- Keep drag-and-drop and drag-resize, using existing backend conflict checks.
- Add waitlist model and UI.
- Align day/week/month semantics with the SimpleStom feedback plan.

Verification:

- Calendar service tests for move/resize, timezone persistence and conflicts.
- Client smoke for slot click, move and resize.

### Stage 5 - Slot Booking Wizard

Goal: transfer SimpleStom's slot modal and preliminary-patient flow.

Work:

- Open booking from a free calendar slot with date, time, doctor and cabinet
  prefilled.
- Add compact patient search and preliminary-patient creation inside the modal.
- Limit duration options from 15 minutes to 3 hours by the actual free window.
- Capture reason for visit and notes.

Verification:

- Front-desk intake tests from preliminary patient to appointment.
- Regression checks for patient/doctor/cabinet conflicts.

### Stage 6 - Patients Workspace

Goal: match `17-feedback-patients.png`.

Work:

- Build a two-pane patient workspace: searchable/sortable patient list on the
  left, selected compact card on the right.
- Keep quick actions to appointment booking and file upload.
- Separate tabs into basic data, tooth chart, clinical history, files,
  calculations/finance and family.
- Ensure clinical history and financial history are not mixed.

Verification:

- Metadata/layout tests.
- Patient summary endpoint tests.

### Stage 7 - Questionnaire And Patient Portal

Goal: bring the SimpleStom questionnaire and portal behavior into EspoDental.

Work:

- Compare SimpleStom adult/child questionnaire spec with the current
  EspoDental questionnaire schema.
- Add missing RU/EN/ES UI fields, ES/RU PDF output rules and signature behavior
  only where EspoDental is missing them.
- Add patient portal sessions/events or extend public tokens to support future
  appointments and reschedule requests without exposing occupied slots.

Verification:

- Public questionnaire tests.
- PDF smoke tests.
- Portal isolation/security tests.

### Stage 8 - Doctor Reception Workspace

Goal: align the Visit page with the SimpleStom reception workspace.

Work:

- Restructure `Visit` detail custom view around complaints, treatment notes,
  services, material consumption, photos, treatment plan, tooth chart, invoice
  and completion checklist.
- Add autosave/personal templates where missing.
- Keep finished visit fields read-only except allowed file/treatment-plan
  additions.

Verification:

- Visit finish transaction tests.
- Invoice and stock write-off idempotency tests.

### Stage 9 - Tooth Chart Contract

Goal: make tooth chart behavior match the SimpleStom clinical contract.

Work:

- Confirm adult, child and mixed FDI rows.
- Confirm whole-tooth and surface states, bridge support behavior, veneer and
  sealant surface rules.
- Show current snapshot and history in the patient workspace without forcing a
  navigation jump.

Verification:

- Tooth chart metadata and renderer tests.
- Snapshot history tests.

### Stage 10 - Services And Inventory

Goal: transfer service catalog and inventory semantics.

Work:

- Redesign service catalog around categories, color, duration, price, cabinet
  requirements and material norms.
- Extend inventory to warehouses, cabinet stock, lots, expiry, FEFO write-off,
  issue-to-cabinet workflow and future order list.

Verification:

- Service material norm tests.
- Stock FEFO, expiry and alert tests.

### Stage 11 - Cash Desk And Shift Closing

Goal: match `14-feedback-cashdesk.png`.

Work:

- Rework cash desk around selected doctor, unpaid invoices, payment wizard,
  advance payment and invoice/factura printing.
- Add cash-shift closing if no safe existing equivalent exists.
- Preserve audit and correction-by-new-record behavior.

Verification:

- Payment, write-off, storno, advance and cash-shift tests.

### Stage 12 - Reports, Payroll And Integrations

Goal: reconcile SimpleStom management modules with existing EspoDental reports.

Work:

- Add saved report definitions only if current dashlets/report endpoints are
  insufficient.
- Compare SimpleStom payroll runs with `SalaryEntry`/`SalaryProfile` and add
  transparent source breakdown where missing.
- Add integration settings/secrets UI for SMTP, WhatsApp and Telegram where
  missing. MCP/AI behavior is out of scope for this migration run.

Verification:

- Report export tests.
- Salary calculation/source tests.
- Integration settings tests without external network calls.

### Stage 13 - Demo Environment

Goal: provide a local demo database that shows the full migrated workflow.

Work:

- Update local runbook.
- Add or extend demo seeding for users, roles, clinics, cabinets, services,
  materials, schedules, patients, waiting states, reschedules, visits, invoices,
  payments, stock alerts, reports and payroll.
- Bring up the local stack with the demo database.

Verification:

- PHPUnit suite.
- Build zip.
- Local Docker smoke.
- Manual browser smoke over the agreed demo script.

## Status

| Stage | Status | Notes |
| --- | --- | --- |
| 0. Migration contract | Completed | Plan added and linked from README; `git diff --check` passed. |
| 1. Gap matrix and acceptance scope | Pending | Starts after stage 0 commit. |
| 2. Visual system foundation | Pending | Depends on stage 1 scope. |
| 3. Dashboard actions and tasks | Pending | Depends on visual foundation and task decision. |
| 4. Calendar feedback UX | Pending | Depends on stage 1 conflict/waitlist decisions. |
| 5. Slot booking wizard | Pending | Depends on stage 4 slot model. |
| 6. Patients workspace | Pending | Can run after visual foundation. |
| 7. Questionnaire and portal | Pending | Requires questionnaire gap check. |
| 8. Doctor reception workspace | Pending | Can reuse existing Visit backend. |
| 9. Tooth chart contract | Pending | Existing renderer is the target baseline. |
| 10. Services and inventory | Pending | Warehouse/lot model is the largest data gap. |
| 11. Cash desk and shift closing | Pending | Cash shift likely needs a new entity. |
| 12. Reports, payroll and integrations | Pending | MCP/AI is excluded from this run. |
| 13. Demo environment | Pending | Final local demo with seeded DB. |
