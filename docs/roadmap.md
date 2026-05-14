# EspoDental Roadmap

Last updated: 2026-05-14

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
- appointment creation from preliminary patient;
- conflict checks for doctor/cabinet/patient;
- questionnaire launch action with token/QR;
- conversion action from preliminary patient to patient;
- direct patient creation blocked for normal roles;
- patient balance initialized at zero;
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
- structured boolean answers;
- signature capture as PNG;
- questionnaire date/version;
- tabular display inside patient card;
- PDF print/save action;
- 1-year expiry alert.

Acceptance:

- receptionist can scan QR on a tablet and hand it to the patient;
- submitted questionnaire is visible on the converted patient;
- PDF contains answers, date and signature.

## Phase 3 - Start Visit And Clinical Work

Goal: doctor starts a visit from an appointment and records clinical work.

Deliverables:

- "Start Visit" action only on eligible appointment/patient;
- appointment status log;
- visit workspace layout for doctor;
- complaints and treatment notes;
- service selection from catalog;
- copied prices and discounts;
- copied material norms with editable consumption before finish;
- before/after visit photo upload;
- visit photos visible in patient card.

Acceptance:

- visit cannot start for preliminary patient;
- visit cannot start twice for the same appointment;
- doctor can prepare all service/material lines before finish.

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
- cash/card/bank/crypto method support;
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

Acceptance:

- the receptionist can complete "payment -> next appointment" in one flow.

## Phase 7 - Dental Clinical Depth

Goal: make the patient card clinically useful.

Deliverables:

- adult tooth chart;
- pediatric tooth chart when child flag is enabled;
- versioned tooth chart snapshots by visit/date;
- family links;
- CBCT/Orthanc links;
- orthodontic card integration;
- specialty-specific visit fields for orthodontics.

Acceptance:

- doctor can see tooth chart history by date and source visit;
- child patients show both adult and pediatric charts.

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
