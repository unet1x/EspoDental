# EspoDental Roadmap

Last updated: 2026-05-15

The roadmap follows vertical clinical/business slices. Each phase should end in
a working UI flow, a CLI/API verification path, and updated documentation.

## Phase 0 - Project Map

Goal: make the repository the source of truth.

Deliverables:

- product spec;
- current-state handoff;
- roadmap;
- acceptance checklist;
- developer runbook.

## Phase 1 - Front Desk Intake

Goal: receptionist can create a preliminary patient, book them and convert them
only after questionnaire completion.

Deliverables:

- `PreliminaryPatient` creation layout ready for reception;
- required phone field;
- default clinic assignment for one-clinic installations;
- technical CRM fields hidden from normal reception screens;
- appointment creation from preliminary patient;
- conflict checks for doctor/cabinet/patient;
- conversion action that launches questionnaire token/QR when needed;
- direct patient creation blocked for normal roles;
- patient balance initialized at zero;
- converted preliminary patient hidden from operational lists;
- appointment becomes eligible for "Start Visit".

Acceptance:

- a receptionist can complete the flow without opening admin screens;
- no duplicate patient is created on repeated conversion attempt;
- conversion preserves source links and audit data.

## Phase 2 - Health Questionnaire

Goal: patient completes the health questionnaire on a tablet and the result is
stored in the patient card.

Deliverables:

- public token route;
- mobile/tablet form;
- structured boolean answers with all visible yes/no items required;
- signature capture as PNG;
- questionnaire date/version;
- tabular display inside patient card;
- compact two-column PDF print/save action;
- 1-year expiry alert.

Acceptance:

- receptionist can scan QR on a tablet and hand it to the patient;
- submitted questionnaire is visible on the converted patient;
- PDF contains answers, date and signature.
- questionnaire schema can be edited in
  `src/files/custom/Espo/Modules/EspoDental/Resources/metadata/dental/questionnaireSchema.json`
  and applies to newly issued forms after rebuild.

## Phase 3 - Start Visit And Clinical Work

Goal: doctor starts a visit from an appointment and records clinical work.

Deliverables:

- "Start Visit" action only on eligible appointment/patient;
- appointment status log;
- visit workspace layout for doctor;
- simplified appointment quick-create form: doctor/cabinet first, duration
  instead of manual end time, default clinic, no source/ownership noise;
- patient card header action for `Записать на прием`, opening the short booking
  modal with the patient prelinked and no full-form escape hatch;
- patient appointment relationship panel does not expose a competing plus
  create flow;
- free-slot dropdown reflects the selected duration immediately;
- appointment display name derived from date/time and status;
- `in_progress` and `finished` appointment statuses controlled by the visit
  workflow, not by manual selection;
- free-slot suggestions use the same blocking rules as save-time validation:
  cabinet free, doctor free across all cabinets/clinics, and patient free;
- complaints and treatment notes;
- service selection from catalog;
- service selection in the visit uses a category-first picker instead of one
  flat list of every service;
- service/catalog navigation via category records: one visible "Service
  Catalog" tab with services inside categories;
- material navigation via category records: one visible "Materials" tab with
  materials inside categories;
- copied catalog prices, catalog currency and discounts; doctors do not edit
  service price/currency;
- copied material norms into `VisitMaterialLine` records with editable
  consumption before finish; doctors edit quantity, not material cost;
- auto-created tooth-chart snapshot when a visit starts, with an immediate
  visual preview on the visit page;
- before/after visit photo upload;
- visit photo quick-add defaults patient/date/name from the visit;
- visit photo quick-add does not ask the doctor for the recorded date; current
  date/time is assigned automatically and can be corrected only by privileged
  users if needed;
- visit page hides appointment, status, stream and invoice panels from the
  doctor workspace;
- service/material lines become server-side read-only once the visit is
  finished;
- visit photos visible in patient card.

Acceptance:

- visit cannot start for preliminary patient;
- visit cannot start twice for the same appointment;
- doctor can prepare all service/material lines before finish.
- after finish, changing service/material lines returns a server conflict.

Next UX refinements still planned in this phase:

- refine the two-select category-first service picker into an expandable tree
  if the clinical UX still feels slow in real use;
- inline quantity editing in relationship panels where EspoCRM allows it.

## Phase 4 - Finish Visit, Invoice And Stock

Goal: finishing the visit creates all downstream records consistently.

Deliverables:

- atomic finish action;
- appointment status `completed`;
- stock write-off movements;
- invoice and invoice lines;
- patient balance ledger update;
- read-only finished visit with privileged correction path;
- error handling if stock cannot be written off or invoice cannot be created.

Acceptance:

- one click finishes visit and creates invoice;
- repeated finish action is idempotent;
- stock balances and patient balance match created ledger records.

## Phase 5 - Cash Desk

Goal: administrator can calculate and close the patient financially.

Deliverables:

- cash desk dashboard/list;
- payment registration;
- admin-editable payment method dictionary, initially including cash, card,
  bank transfer and optional crypto;
- partial payment support;
- write-off and reversal/storno flows;
- invoice/act/receipt print actions;
- patient financial tab.

Acceptance:

- invoice cannot exist without visit;
- payment changes patient balance;
- corrections are auditable and do not silently edit posted data.

## Phase 6 - Next Appointment Loop

Goal: after payment, the patient can be booked for the next visit without losing
context.

Deliverables:

- "Book next appointment" action from visit, invoice and patient card;
- carry patient/doctor/service context where useful;
- show future appointments above past visits in patient history.
- define the schedule availability model needed by booking: doctor shifts,
  additional shifts, closed periods, cabinet availability and doctor/assistant
  pairings.

Acceptance:

- the receptionist can complete "payment -> next appointment" in one flow.
- slots outside the doctor's active shift are not offered unless an additional
  shift exists.
- assistant is inferred from the shift pairing where available.

## Phase 7 - Dental Clinical Depth

Goal: make the patient card clinically useful.

Deliverables:

- adult tooth chart;
- surface-level adult tooth chart with visual defects per surface, following
  the reference-style layout with tooth silhouettes and occlusal/incisal
  surface diagrams;
- pediatric tooth chart when child flag is enabled;
- automatic child flag for patients aged 0-14 based on birth date;
- child visits display both adult and pediatric charts;
- parent/guardian fields for manual entry;
- optional linked parent/guardian `Patient` relationship, with contact and
  notification preferences inherited from the linked parent when present;
- versioned tooth chart snapshots by visit/date;
- admin-editable tooth-chart condition dictionary with colors;
- admin-editable tooth-surface labels for the clinical editor;
- family links;
- CBCT/Orthanc links;
- orthodontic card integration;
- specialty-specific visit fields for orthodontics.

Acceptance:

- doctor can see tooth chart history by date and source visit;
- child patients show both adult and pediatric charts;
- parent-linked child appointment reminders use the linked parent's preferred
  communication method; manually entered parent data falls back to child card
  communication settings.

## Phase 8 - Roles And Workspaces

Goal: every clinic role gets a clear home screen and allowed actions.

Deliverables:

- administrator dashboard;
- doctor dashboard;
- assistant dashboard;
- manager dashboard;
- head nurse/stock dashboard;
- role ACL review;
- default menus per role where supported by EspoCRM.

Acceptance:

- users do not see irrelevant or dangerous actions for their role.

## Phase 9 - Infrastructure And Integrations

Goal: prepare for the Proxmox/VM target and external communication automation.

Deliverables:

- Proxmox VM deployment notes for AOOSTAR WTR MAX;
- backup/restore runbook for VM and Docker volumes;
- staging upgrade workflow;
- integration adapter layer for Telegram, WhatsApp and email;
- MCP server design;
- local LLM "virtual administrator" design;
- message/outbox audit model.

Acceptance:

- CRM can be migrated from Synology to VM from backup;
- bots can draft CRM actions but cannot silently perform risky medical or
  financial mutations.

## Phase 10 - Reports, Payroll And Management

Goal: manager gets operational control.

Deliverables:

- revenue reports;
- doctor productivity reports;
- chair/cabinet utilization;
- no-show/cancellation reports;
- inventory reports;
- payroll calculations for percent, hourly and fixed salary models.

Acceptance:

- reports are based on structured workflow data, not manually edited totals.
