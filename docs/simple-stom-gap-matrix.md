# SimpleStom Gap Matrix

Last updated: 2026-05-26

This document is the stage 1 acceptance scope for moving SimpleStom behavior
and visual language into EspoDental. SimpleStom remains a read-only reference at
`/Users/unet1x/Codex/SimpleStom`.

Status values:

- `exists`: EspoDental already has the concept and only needs styling or
  workflow wiring.
- `extend`: EspoDental has the base concept, but SimpleStom requires additional
  fields, service behavior, UI state or demo data.
- `new`: add a new module entity/service/view because no safe native equivalent
  exists.
- `defer`: keep outside the first migration pass unless a later stage needs it.
- `do-not-port`: explicitly excluded from this migration run.

## Source Inputs

The matrix is based on the SimpleStom documents and renders listed in
`docs/simple-stom-migration-plan.md`, with final UI priority given to:

- `mockups/13-feedback-calendar.png`
- `mockups/14-feedback-cashdesk.png`
- `mockups/15-feedback-dashboard.png`
- `mockups/16-feedback-inventory.png`
- `mockups/17-feedback-patients.png`

Earlier mockups remain the source for appointment wizard, patient portal,
reception, services, users/roles, schedule settings and payroll.

## Entity And Data Matrix

| SimpleStom item | EspoDental target | Status | Stage | Acceptance decision |
| --- | --- | --- | --- | --- |
| Clinic | `Clinic` | exists | 13 | Use existing multi-clinic boundary; demo seed must show at least one clinic with schedule, currency and locale. |
| Cabinet | `Cabinet` | extend | 4, 10 | Keep entity; add or encode procedure capabilities before slot filtering depends on them. |
| CabinetCapability | new or cabinet settings | new | 4, 10 | Needed for procedure-specific availability; model choice is made before calendar stage implementation. |
| StaffProfile | `User`, Espo roles, `SalaryProfile` | extend | 3, 12 | Existing users/roles/salary profiles remain canonical; dashboard and schedule views need role-aware staff filters. |
| Group | EspoCRM `Team` | exists | 13 | Use teams unless tests prove SimpleStom group semantics need a separate scope. |
| PrePatient | `PreliminaryPatient` | exists | 5 | Reuse conversion workflow; booking wizard must create from a slot without leaving the modal. |
| Patient | `Patient` | extend | 6, 7 | Existing patient card remains source of truth; add missing flags, portal links and workspace summary data. |
| PatientFlagDefinition | new entity or patient tag/status fields | new | 6 | Required only if searchable custom flags cannot be represented with current fields. |
| FamilyLink | parent/child links or new relation | extend | 6 | Current parent/child links cover children; broader family graph is implemented only if the workspace requires it. |
| Appointment | `Appointment` | extend | 4, 5, 7 | Keep current status log/conflict hooks; align statuses, planned services, portal requests and holds. |
| AppointmentPlannedService | relation or appointment payload | extend | 5, 10 | Booking must remember requested service/duration without forcing invoice creation. |
| AppointmentWaitlistEntry | new entity | new | 4 | Required by the final calendar right-side waiting panel. |
| AppointmentRescheduleRequest | new entity or `AssistantActionProposal` | new | 7 | Patient portal requests need auditable review and no direct mutation of occupied slots. |
| AppointmentSlotHold | new service state/entity | new | 7 | Needed to protect free slots during portal reschedule confirmation. |
| PatientPortalSession | token/session model | new | 7 | Current questionnaire token is narrower than the SimpleStom patient portal. |
| Task | Espo task or new module entity | extend | 3 | Prefer native EspoCRM Task if it supports assignment, status, clinic/team visibility and dashboard aggregates. |
| Reception | `Visit` | exists | 8 | Existing Visit workflow is the target; redesign the workspace around SimpleStom reception layout. |
| ReceptionService | `VisitServiceLine` | exists | 8 | Existing service lines stay canonical. |
| ReceptionMaterialUsage | `VisitMaterialLine` | exists | 8, 10 | Existing planned/actual material lines stay canonical; later FEFO lot write-off extends stock semantics. |
| DentalChartSnapshot/Event | `ToothChartSnapshot` | exists | 9 | Existing renderer/snapshots remain the baseline; confirm SimpleStom states and mixed dentition. |
| PatientFile | `Attachment`, `VisitPhoto`, questionnaire PDFs | extend | 6, 8 | Add unified patient file UX; do not duplicate storage. |
| ServiceCategory | `ServiceCategory` | exists | 10 | Existing catalog categories remain canonical. |
| Service | `Service` | extend | 5, 10 | Add UI for color, expected duration, procedure requirements and material norms where missing. |
| InventoryWarehouse | new warehouse/storage layer | new | 10 | Required by feedback inventory view and cabinet stock movement. |
| StockLot | new lot/expiry layer | new | 10 | Required for expiry tracking and FEFO write-off. |
| InventoryTransaction | `StockMovement` | extend | 10 | Keep immutable posted movement pattern; add warehouse/lot/cabinet dimensions. |
| Low stock alert | `LowStockAlert` | exists | 10 | Existing threshold job stays; UI must expose feedback render lists. |
| Invoice | `Invoice` | exists | 11 | Keep numbering, issue/storno and immutability rules. |
| InvoiceLine | `InvoiceLine` | exists | 11 | Existing line calculation remains canonical. |
| Payment | `Payment` | extend | 11 | Add advance and cash desk wizard behavior where missing; preserve correction-by-new-record. |
| CashShift | new entity/service | new | 11 | Required if no existing daily cash-closing equivalent is found. |
| FinancialDocumentSequence | invoice/payment hooks | extend | 11 | Existing numbering covers invoices/payments; receipt/factura output needs final comparison. |
| ReportDefinition | report service/dashlet layer | extend | 12 | Add saved definitions only if existing report endpoints cannot represent SimpleStom reports. |
| PayrollRun | `SalaryEntry`, `SalaryBonus`, `SalaryProfile` | extend | 12 | Existing salary entries remain canonical; add transparent run/source breakdown if missing. |
| IntegrationSettings | settings and integration docs/services | extend | 12 | Telegram exists; SMTP/WhatsApp UI and secret storage need comparison. |
| MCP/AI runtime | none | do-not-port | 12 | Excluded from this migration run. Existing repository docs remain untouched, but no MCP behavior is added. |
| SimpleStom FastAPI backend | none | do-not-port | all | Do not port runtime stack; implement behavior in EspoCRM PHP services/controllers. |
| SimpleStom React/Tailwind app shell | none | do-not-port | all | Do not embed the app; recreate the visual language in EspoDental client views. |

## Backend And API Matrix

| Area | Current EspoDental baseline | Status | Stage | Acceptance decision |
| --- | --- | --- | --- | --- |
| Calendar free slots | `CalendarService::findFreeSlots`, `Calendar` controller | extend | 4, 5 | Add SimpleStom slot-first semantics, procedure duration limits, waitlist and portal-safe filtering. |
| Calendar move/resize | `CalendarService::moveAppointment`, conflict hooks | extend | 4 | Keep existing conflict enforcement; verify drag, resize and timezone persistence. |
| Appointment lifecycle | appointment hooks, status log, visit start flow | extend | 4, 5, 8 | Align status vocabulary while preserving current audit history. |
| Preliminary patient conversion | `PreliminaryPatientConversion` service | exists | 5 | Booking wizard uses existing service rather than duplicating conversion. |
| Health questionnaire | `HealthQuestionnaireService`, public controller, PDF tools | extend | 7 | Compare adult/child RU/EN/ES fields, signature and one-year expiry against SimpleStom spec. |
| Patient portal | questionnaire entry point only | new | 7 | Add future appointments and reschedule requests without exposing occupied slots. |
| Patient summary | scattered patient, visit, invoice and file data | extend | 6 | Add a compact summary service if current client views cannot load the two-pane workspace efficiently. |
| Visit finish | existing visit service/hooks | exists | 8 | Preserve idempotent finish, invoice creation and stock write-off behavior. |
| Tooth chart history | `ToothChartSnapshot` service/views | exists | 9 | Verify SimpleStom state coverage and history placement in patient workspace. |
| Stock totals | `StockCalculator`, `StockService`, `StockMovement` | extend | 10 | Add warehouse, cabinet stock and lot/expiry dimensions without mutating posted movements. |
| Finance corrections | invoice/payment handlers and hooks | exists | 11 | Keep storno/refund/correction-by-new-record contract. |
| Cash shift closing | no confirmed entity | new | 11 | Add if feedback cash desk cannot be represented with reports alone. |
| Reports | `ReportService`, report dashlets/tests | extend | 12 | Keep existing report endpoints; add saved templates only for missing SimpleStom reports. |
| Payroll | `SalaryService`, salary profiles/entries/bonuses | extend | 12 | Compare payroll run detail and source transparency before adding entities. |
| Demo seeding | bootstrap workspace seeder | extend | 13 | Add complete SimpleStom-like workflow data to the existing idempotent seeder. |

## UI Screen Matrix

| SimpleStom screen/render | EspoDental target | Status | Stage | Acceptance decision |
| --- | --- | --- | --- | --- |
| `15-feedback-dashboard.png` | role dashboards/dashlets | extend | 3 | Dashboard becomes an operational action center: waiting patients, proposals, tasks, alerts and weekly work. |
| `13-feedback-calendar.png` | resource calendar dashlet/view | extend | 4 | Implement compact toolbar, mini-calendar, waiting/cancelled side panel, slot-first interactions and preserved drag/resize. |
| Appointment wizard | appointment modal | extend | 5 | Slot click opens compact patient search/prepatient wizard with bounded duration choices. |
| `17-feedback-patients.png` | patient workspace | extend | 6 | Build two-pane list/detail workspace with separate clinical, file, finance and family tabs. |
| Patient portal | public patient portal entry | new | 7 | Add secure future-appointment and reschedule screens; questionnaire remains part of the portal. |
| Reception | `Visit` detail custom view | extend | 8 | Align to SimpleStom workbench: notes, services, materials, photos, chart, invoice and finish checklist. |
| Tooth chart | patient/visit chart panels | extend | 9 | Ensure the chart can be reviewed inside patient workspace without navigation jumps. |
| Services | service catalog views | extend | 10 | Add category/color/duration/requirements/material norms UI. |
| `16-feedback-inventory.png` | inventory workspace | extend | 10 | Implement storage-place lists, below-threshold materials, cabinet issue flow, expiry/future order areas. |
| `14-feedback-cashdesk.png` | cash desk workspace | extend | 11 | Implement selected doctor filter, unpaid invoices, payment wizard, advance payment and shift closing. |
| Users and roles | installer roles + Espo admin | exists | 13 | Keep native EspoCRM administration; seed roles already match clinic staff categories. |
| Schedule settings | schedule/shift template views | extend | 4, 13 | Confirm doctor/cabinet availability setup supports the final booking UX. |
| Payroll | salary views/dashlets | extend | 12 | Keep existing salary module; add run-style summary if required by SimpleStom parity. |

## Visual Scope

The SimpleStom visual language is not a global EspoCRM skin replacement. It is
applied to EspoDental workspaces and dashlets introduced or redesigned by this
plan.

| Token or pattern | Decision |
| --- | --- |
| Page background | Soft green `#edf3ef` for SimpleStom-style workspaces. |
| Primary action | Green `#438f7e`; reserve for the main action in each workspace. |
| Surfaces | White panels with compact spacing and restrained borders. |
| Layout | Dense operational split panels, toolbars and tables; no marketing hero sections. |
| Navigation | Use EspoCRM shell/navigation; do not port SimpleStom React sidebar wholesale. |
| Cards | Use cards only for repeated items, summaries and modals; avoid nested cards. |
| Badges | Compact status and risk badges with clear color meaning. |
| Responsiveness | All key screens must preserve readable tables/panels on laptop width before mobile refinements. |

## Demo Acceptance Scope

The final local demo database must support this script end to end:

1. Log in as manager and see the SimpleStom-style dashboard with tasks,
   reschedule proposals, waiting patients, alerts and weekly workload.
2. Open the calendar, switch day/week, pick a free slot, create a preliminary
   patient, assign doctor/cabinet/service/duration and see conflict protection.
3. Add a patient to the waitlist, move the appointment by drag/drop, resize it,
   and confirm the right-side waiting/cancelled panel updates.
4. Open a patient workspace, review demographics, clinical history, tooth chart,
   files, finance and family without mixing clinical and cash data.
5. Send a questionnaire link, complete an adult or child questionnaire in a
   public flow, sign it and generate a RU or ES PDF.
6. Use patient portal future appointments to request a reschedule; review the
   request from the dashboard/calendar without exposing occupied slots publicly.
7. Start a visit from an arrived appointment, add services, material usage,
   tooth chart changes, files/photos and finish the visit once.
8. Open cash desk, filter by doctor, take payment or advance payment, print the
   invoice/factura, then close the cash shift.
9. Receive stock into a warehouse/lot, issue material to a cabinet, consume by
   FEFO during a visit, and see low-stock/expiry lists.
10. Review saved management reports and payroll/source breakdown for the demo
    staff.

## Stage Acceptance Gates

| Stage | Minimum gate before commit |
| --- | --- |
| 2. Visual system foundation | Visual tokens/helpers documented, client syntax smoke passes, metadata tests pass. |
| 3. Dashboard actions and tasks | Task/proposal/status aggregates are tested and role dashboard templates are documented. |
| 4. Calendar feedback UX | Move/resize/conflict tests pass and the UI exposes waiting/cancelled side panels. |
| 5. Slot booking wizard | Preliminary-patient booking from a slot is covered by tests. |
| 6. Patients workspace | Patient summary/workspace contract is tested and clinical vs financial sections stay separated. |
| 7. Questionnaire and portal | Public questionnaire, PDF and portal isolation tests pass. |
| 8. Doctor reception workspace | Visit finish, invoice creation and stock write-off stay idempotent. |
| 9. Tooth chart contract | Snapshot/state coverage is tested for adult, child and mixed dentition. |
| 10. Services and inventory | Warehouse/lot/FEFO and service material norms are tested. |
| 11. Cash desk and shift closing | Payment, advance, storno/refund and cash-shift tests pass. |
| 12. Reports, payroll and integrations | Reports/payroll tests pass without external network calls. |
| 13. Demo environment | PHPUnit suite, build, local stack smoke and manual demo script are completed. |

## Open Decisions

These decisions must be resolved at the first implementation stage that depends
on them:

| Decision | Resolve by | Default if still ambiguous |
| --- | --- | --- |
| Whether cabinet procedure capabilities are an entity or JSON settings | Stage 4 | Add a small entity if slot filtering needs queryable requirements. |
| Whether patient flags use tags/status or a new definition entity | Stage 6 | Use a new definition entity only for configurable searchable flags. |
| Whether broader family graph needs a relation entity | Stage 6 | Keep parent/child links unless demo workflows need more. |
| Whether portal reschedule requests use `AssistantActionProposal` or a dedicated entity | Stage 7 | Use a dedicated request entity linked to appointment and patient, plus proposal for staff approval if needed. |
| Whether cash shift is a report or entity | Stage 11 | Add `CashShift` if closing needs immutable cashier totals and audit. |
| Warehouse/lot granularity for inventory | Stage 10 | Add warehouse and lot entities; keep posted `StockMovement` immutable. |
| Patient portal authentication | Stage 7 | Use signed time-limited tokens first; add OTP only if required. |
| Doctor cash desk filtering enforcement | Stage 11 | Enforce in backend if role access depends on selected doctor; otherwise keep as UI filter. |
