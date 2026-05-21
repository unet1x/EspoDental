# EspoDental Product Specification / Техническое задание

Last updated: 2026-05-22

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
   - the receptionist starts conversion from `PreliminaryPatient`;
   - the system opens a QR/token flow;
   - the patient fills the form on a tablet;
   - all visible yes/no medical questions must be answered;
   - boolean health answers are stored structurally;
   - the signature is stored as an image;
   - questionnaire date is saved.
5. Only after the questionnaire is complete, the preliminary patient can be
   converted into `Patient`.
6. During conversion:
   - all shared personal fields are copied;
   - the questionnaire is linked to the patient;
   - the originating preliminary patient remains linked for audit;
   - the originating preliminary patient is hidden from operational lists;
   - the patient balance is created with value `0`;
   - the patient becomes eligible for a visit.
7. The appointment can then be started as a `Visit`.
8. During the visit:
   - doctor and patient are fixed from the appointment;
   - complaints and treatment notes are filled;
   - services are selected from the service catalog;
   - service prices are copied into visit lines;
   - material consumption norms are copied from service norms into visit
     material lines;
   - doctor or assistant can adjust prepared material consumption before
     finish, with audit trail;
   - before/after photos can be uploaded and linked to the visit.
9. Finishing the visit performs one atomic clinical-financial operation:
   - appointment status becomes `finished`;
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
- A converted `PreliminaryPatient` should not remain in normal reception
  worklists. Conversion analytics must use the patient conversion source link,
  not an active duplicate preliminary record.
- Direct patient import is allowed only through a dedicated migration/admin
  pathway with source, timestamp and operator recorded.
- `Visit` must be started from an `Appointment`.
- `Invoice` must be linked to a finished or finishing `Visit`.
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

Module-level clinical/financial dictionaries are configured only in the
EspoDental admin settings page:

- payment methods used by cashdesk payment acceptance;
- tooth-chart condition options and their display colors;
- tooth-surface labels/descriptions shown in the clinical editor.

Operational users select values from these configured lists but do not manage
the dictionaries from patient, visit or payment workspaces.

## 5. Core Modules

### Preliminary Patients

Fields:

- last name, first name, middle name;
- gender;
- phone, international format, required;
- unique email where present;
- status: created, booked, processed, no-show;
- birth date.
- clinic, preferably pre-filled from the EspoDental default clinic setting
  when the installation has one active clinic.

List view: full name, phone, email, appointment date.

Allowed actions:

- create;
- book appointment;
- start conversion, which launches the questionnaire flow when needed;
- convert to patient only after questionnaire completion;
- no direct "start visit" before conversion;
- no manual deletion from normal UI/API flows. Preliminary patients are hidden
  by controlled conversion logic, not removed by users.

Reception forms should avoid technical CRM noise. `status`, `assignedUser`,
teams, conversion internals and questionnaire token relationships are hidden
from the normal create/detail workspace unless needed by an administrator.

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
- parent/guardian data when child flag is enabled;
- optional linked parent/guardian patient when the parent is treated in the
  same clinic.

Patient records must not be manually deleted from normal UI/API flows. Archival
or merge/correction flows must be explicit and auditable.

Child handling:

- if patient age is 0-14 years inclusive, `child` is set automatically from
  birth date;
- the child flag can still be reviewed manually by authorized users;
- child patients show both adult and pediatric tooth charts during a visit;
- parent/guardian data can be entered manually;
- alternatively, a child can be linked to another `Patient` as parent/guardian;
- when a linked parent patient exists, parent contact fields and notification
  preferences are inherited from that parent;
- when only manual parent data exists, reminders follow the child card's
  communication settings.

Patient card tabs:

- tooth chart;
- visit history;
- health questionnaire;
- files and photos;
- financials;
- orthodontic card;
- family;
- CBCT/Orthanc links.

Files and photos in the patient card must be clinically contextual: visit
photos show the originating visit/date and open the source `VisitPhoto`, while
questionnaire files expose the generated PDF and stored signature from the
source `HealthQuestionnaire`. Normal patient-card panels are read-oriented for
these records; photos are added from visits and questionnaires are issued
through the QR/token flow.

The health questionnaire tab must show a readable table of answers grouped by
the configured questionnaire schema, the date and version, alert flags, and
generated files. The patient card must also show a visible warning when the
latest questionnaire is expired or has medical alert answers. Questionnaire PDF
output must be compact enough for clinic printing; the current target layout is
two answer columns plus signature.

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
Reception booking forms should show only operational fields: doctor, cabinet,
date/free-time slot, duration, clinic, confirmation/reminder and complaints.
Assistant is not selected manually in booking; it will come from the future
doctor/assistant schedule pairing. Clinic should default from module settings
for one-clinic installations. End time is derived from duration.
`in_progress` and `finished` are system statuses owned by the visit workflow,
not manual booking choices.
Patient cards should expose a clear header action `Записать на прием`; users
should not have to find the small relationship-panel plus button for the normal
booking flow. This action must open the short booking modal with the patient
already linked and without a generic full-form button. The patient appointment
relationship panel should be read/list oriented for booking; it must not expose
a competing create/select plus-button workflow.
After doctor, cabinet, date and duration are known, the start-time control
should offer free slots from the resource calendar as a date plus free-time
dropdown, without a separate technical action button or manual datetime field.
The offered slot end time must reflect the selected duration immediately, not
fall back to the default 30-minute duration.
Slots are stored as UTC datetimes, but all operational booking feedback must
use the clinic timezone: slot labels, appointment display names and the success
notification after patient booking must show the clinic-local time.
Free-slot suggestions and save-time validation must use the same resource
rules: the cabinet must be free, the doctor must not be booked in any other
cabinet/clinic, and the patient/preliminary patient must not have an overlapping
blocking appointment.

Doctor availability must eventually come from explicit work shifts, not from a
global clinic day window. If the doctor has a first shift, second-shift slots
must not be offered until an additional shift is opened. Assistant assignment
must be derived from the doctor/assistant shift pairing instead of manual
appointment entry.

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

The doctor workspace should not show cash-desk noise. Status, source
appointment, stream and invoice panels are hidden in normal visit detail.
Services show catalog service, quantity, discount and calculated amount.
Materials show material, planned quantity, actual quantity and unit; cost fields
are hidden from doctors. Service price/currency/VAT and material cost are
copied from catalog records and are not manually edited during the visit.

Each started visit has a tooth-chart snapshot and a visual preview on the visit
page. The snapshot can be edited while the visit is in progress and is linked to
the visit/date for history.

Tooth chart UI target:

- adult and pediatric charts use FDI numbering;
- each tooth has surface-level state, not only one whole-tooth condition;
- the visual chart shows occlusal/incisal center and surrounding surfaces, with
  defects rendered on the affected surface;
- tooth root/crown silhouette is shown above/below the surface circles where
  practical, similar to the provided visual reference;
- editing a tooth opens a surface editor for condition and notes;
- snapshots store structured per-tooth/per-surface state for future search,
  reports and printouts.

Finishing a visit must generate the downstream financial and inventory records.
After finish, service and material lines are read-only except for a privileged
correction path.

### Service And Material Catalogs

The left navigation should show one entry for services and one entry for
materials:

- `Catalog services` / `Каталог услуг`: opens service categories. Services live
  inside categories. A service stores expected duration, price, VAT and material
  consumption norms. Visit service selection must be category-first, not a flat
  list of every service.
- `Materials` / `Материалы`: opens material categories. Materials live inside
  categories and store unit, stock thresholds, derived current stock and reorder
  data.

Direct service/material entity tabs are hidden from the normal workspace; users
navigate through categories as folders.

### Cash Desk

Cash desk supports:

- invoice issue;
- payment by cash, card, bank transfer and optional crypto;
- invoice-linked payments are allowed only for issued or partially paid invoices
  and must not exceed the current invoice balance;
- prepayments without an invoice are allowed as patient credit;
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
- current quantity derived from stock movements;
- reorder threshold;
- clinic where applicable.

Low stock alerts go to the manager or head nurse role.
Posted stock movements are not silently edited or deleted. Corrections are
entered as additional receipt, write-off, return or adjustment movements so the
stock history remains auditable.

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
