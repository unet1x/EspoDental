# EspoDental Product Specification / Техническое задание

Last updated: 2026-05-14

## 1. Product Goal

EspoDental is a self-hosted dental medical information system built on
EspoCRM 9.2.x for a small or mid-sized dental clinic.

Initial target:

- 1 clinic.
- Up to 5 cabinets.
- Up to 30 doctors.
- Assistants, reception desk, manager and super-admin roles.
- Future expansion to 2 clinics in one installation.

The system must open as a ready-to-work dental workspace. A receptionist,
doctor or manager should not need to guess which entity to open next.

## 2. Mandatory Patient Flow

The primary flow is strict and connected end to end:

1. A person calls the clinic and is created as a `PreliminaryPatient`.
2. The preliminary patient is booked into `Appointment`.
3. The patient arrives at the clinic.
4. Before treatment, the patient completes a health questionnaire:
   - the receptionist opens a QR/token flow;
   - the patient fills the form on a tablet;
   - boolean health answers are stored structurally;
   - the signature is stored as an image;
   - questionnaire date is saved.
5. Only after the questionnaire is complete, the preliminary patient can be
   converted into `Patient`.
6. During conversion:
   - all shared personal fields are copied;
   - the questionnaire is linked to the patient;
   - the originating preliminary patient remains linked for audit;
   - the patient balance is created with value `0`;
   - the patient becomes eligible for a visit.
7. The appointment can then be started as a `Visit`.
8. During the visit:
   - doctor and patient are fixed from the appointment;
   - complaints and treatment notes are filled;
   - services are selected from the service catalog;
   - service prices are copied into visit lines;
   - material consumption norms are copied from service norms;
   - doctor can adjust consumption before finish, with audit trail;
   - before/after photos can be uploaded and linked to the visit.
9. Finishing the visit performs one atomic clinical-financial operation:
   - appointment status becomes `completed`;
   - stock write-off movements are created;
   - invoice and invoice lines are created;
   - patient balance is updated;
   - visit becomes read-only except for privileged corrections.
10. Reception/cash desk processes the invoice:
   - register payment;
   - print invoice/act/receipt where applicable;
   - handle reversal, write-off or complaint flow with audit.
11. Patient can be booked for the next appointment from the completed visit or
   from the patient card.

## 3. Hard Business Invariants

These rules are product requirements, not only UI preferences:

- A normal user must not create a `Patient` directly. A patient is created
  from `PreliminaryPatient` conversion.
- Direct patient import is allowed only through a dedicated migration/admin
  pathway with source, timestamp and operator recorded.
- `Visit` must be started from an `Appointment`.
- `Invoice` must be linked to a completed or finishing `Visit`.
- `InvoiceLine` must come from `VisitServiceLine` or a documented correction.
- Stock balance changes must happen only through `StockMovement`.
- Financial balance must be ledger-based. The displayed patient balance is a
  derived value, not a manually edited field.
- Posted invoices, payments and stock movements must not be silently edited.
  Corrections require reversal/correction records.
- Appointment status changes must be logged with timestamp and user.
- Medical notes, questionnaire answers, signatures and photos must be
  attributable to patient, visit and operator.
- Questionnaire older than 1 year must raise a visible alert.

## 4. Core Roles

- Super-admin: installation, system settings, module upgrades.
- Manager: full clinic visibility, reports, payroll, settings.
- Administrator / Reception: preliminary patients, appointments, questionnaire
  launch, conversion, invoices and payments.
- Doctor: own appointments, visits, clinical notes, tooth chart, visit photos.
- Assistant: helps with visits, photos, materials, limited edit rights.
- Stock manager / head nurse: materials, reorder thresholds, stock alerts.

## 5. Core Modules

### Preliminary Patients

Fields:

- last name, first name, middle name;
- gender;
- phone, international format;
- unique email where present;
- status: created, booked, processed, no-show;
- birth date.

List view: full name, phone, email, appointment date.

Allowed actions:

- create;
- book appointment;
- launch questionnaire;
- convert to patient after questionnaire;
- no direct "start visit" before conversion.

### Patients

Contains all preliminary patient fields plus:

- address;
- notes;
- Telegram;
- WhatsApp;
- preferred contact method;
- VIP flag;
- restrictions flag;
- paper card number, unique;
- first completed visit date;
- creation date;
- child flag;
- parent/guardian data when child flag is enabled.

Patient card tabs:

- tooth chart;
- visit history;
- health questionnaire;
- files and photos;
- financials;
- orthodontic card;
- family;
- CBCT/Orthanc links.

### Appointments

Appointment is a resource-calendar record with:

- patient or preliminary patient depending on stage;
- doctor;
- assistant where applicable;
- cabinet;
- clinic;
- date/time/duration;
- status;
- creator/confirming user;
- status log.

The calendar must prevent conflicts for doctor, cabinet and patient.

### Visits

Visit is the clinical workspace:

- doctor;
- patient;
- appointment;
- paper card number;
- complaints;
- treatment notes;
- services;
- material consumption;
- discounts;
- tooth chart changes;
- photos;
- orthodontic-specific fields when applicable.

Finishing a visit must generate the downstream financial and inventory records.

### Cash Desk

Cash desk supports:

- invoice issue;
- payment by cash, card, bank transfer and optional crypto;
- write-off;
- reversal/storno;
- complaint handling;
- receipt creation;
- invoice/act printing;
- patient balance history.

### Inventory

Material records include:

- name;
- category;
- measurement unit;
- sale/package unit;
- supplier reorder URL;
- current quantity;
- reorder threshold;
- clinic where applicable.

Low stock alerts go to the manager or head nurse role.

## 6. Infrastructure Requirements

Current Synology target:

- EspoCRM in Container Manager / Docker Compose.
- Database data outside containers: `/volume1/docker/espodental/bd`.
- EspoCRM files and uploads outside containers: `/volume2/espodental/data`.
- Module source mounted separately: `/volume1/espomodule`.
- Environment variables stored in `.env`.

Future target:

- AOOSTAR WTR MAX host.
- Proxmox installed on bare metal.
- Dedicated VM for EspoCRM.
- Docker Compose inside the VM unless a later decision favors native services.
- Same external-data principle inside the VM: database, uploads and module
  sources must be easy to back up and migrate.
- Separate staging VM or staging Compose stack for upgrade testing.

## 7. Integrations And Virtual Administrator

The system should be designed for future automation, not tightly coupled to one
bot implementation.

Required integration directions:

- Telegram reminders and operator notifications.
- WhatsApp communication through a replaceable provider adapter.
- Email for reminders, documents and backup alerts.
- MCP server for controlled access to CRM actions and data.
- Local LLM integration as a "virtual administrator".

Virtual administrator target scope:

- answer common patient questions using clinic-approved templates;
- suggest appointment slots;
- collect preliminary patient data;
- remind patients about appointments;
- ask patients to update health questionnaire;
- create draft records in CRM, not silently finalize medical/financial actions;
- escalate ambiguous or risky requests to a human administrator.

Design requirements:

- all bot/LLM actions must be logged;
- every external message should have channel, recipient, template and status;
- LLM must not directly mutate critical medical or financial records;
- MCP tools must expose narrow, permission-checked actions;
- PHI/medical data access must be minimized and auditable.

## 8. Engineering Principles

- Build vertical slices, not isolated tables.
- Domain actions should be explicit services: convert patient, start visit,
  finish visit, register payment, reverse payment, write off stock.
- Use state machines or validated transitions for statuses.
- Keep action handlers idempotent where practical.
- Store immutable audit records for clinical, financial and inventory events.
- Prefer structured data over free text where reports will be needed.
- Keep module install/bootstrap repeatable for Docker and extension installs.
- Preserve compatibility with EspoCRM 9.2.x first.
