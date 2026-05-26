# EspoDental Product Development Plan

Дата аудита: 2026-05-26.

Источник сравнения: `/Users/unet1x/Codex/SimpleStom` прочитан как read-only reference. В `SimpleStom` проверены продуктовые документы, функциональная спецификация, сценарии, UI map, модель данных, roadmap, decision log, screen specs, development plan, questionnaire spec, implementation log, redesign plan, demo runbook, customer feedback renders, production readiness, backend/frontend структура и mockups.

Цель документа: зафиксировать, как довести EspoDental до удобной стоматологической МИС внутри EspoCRM, не копируя SimpleStom как отдельное приложение.

Связанный decision log: [docs/espo-dental-product-decisions.md](espo-dental-product-decisions.md).

## 1. Product Position

EspoDental должен быть не набором CRUD-страниц, а рабочим ядром стоматологической CRM/МИС для клиники или небольшой сети:

- 1-5 клиник без жесткого технического лимита;
- несколько кабинетов с параметрами доступности;
- администратор записывает пациента без ручной сверки врача, кабинета и уборки;
- врач ведет прием из одного рабочего места;
- руководитель видит кассу, склад, загрузку врачей/кабинетов, отчеты и зарплату;
- пациент получает напоминания, анкету, подтверждения и безопасный портал;
- все клинические, финансовые и складские изменения аудируются.

EspoCRM остается платформой: пользователи, ACL, роли, teams, attachments, metadata, dashlets, extension install и deployment. SimpleStom переносится как продуктовый и UX-референс.

## 2. What EspoDental Already Has

По документации и метаданным текущий модуль уже содержит основное ядро:

- front desk flow: `PreliminaryPatient -> Appointment -> Questionnaire -> Patient -> Visit`;
- блокировку прямого создания `Patient`, `Visit`, `Invoice` вне доменных сервисов;
- appointment conflict checks по врачу, кабинету и пациенту;
- doctor shifts, templates, cabinet closures and assistant assignment;
- health questionnaire с QR/token, структурными ответами, подписью и PDF;
- patient portal sessions/events and reschedule request entities;
- patient workspace panels: history, finance, files, questionnaire summary, care summary, tooth chart history, CBCT/Orthanc;
- visit workspace: complaints, treatment notes, services, material lines, photos, tooth chart, finish;
- service/category and material/category catalogs;
- warehouses, stock lots, immutable stock movements and low-stock alerts;
- invoice, invoice lines, payments, refunds, storno, advance application and cash shifts;
- reports, report definitions, salary profiles/entries/source breakdown;
- integration settings, notification log, WhatsApp/Telegram/email direction, MCP/assistant proposal boundary;
- SimpleStom visual helper and several SimpleStom-style dashlets/workspaces.

Это означает, что ближайшая работа должна быть не фундаментальной переписью, а product hardening и UX-полировка поверх существующих сервисов.

## 3. Main Product Gaps

### 3.1 UX still needs a final operational pass

Документы уже фиксируют post-parity gaps:

- календарь еще должен точнее соответствовать feedback render: видимые фильтры врача/кабинета, mini-calendar, right panel toggle, slot-first behavior;
- patient workspace нужно сделать богаче: возраст, канал связи, теги/аллергии, ближайшая запись, долг/аванс, быстрые действия;
- cash desk должен работать как операционное рабочее место: выбранный врач, unpaid invoices, selected invoice action panel, payment wizard;
- склад требует отдельного inventory workspace поверх warehouses/lots/cabinet issue/expiry/future orders, а не только report dashlet;
- часть видимых строк и badge-статусов нужно локализовать и привести к русскому рабочему языку.

### 3.2 Product decisions still need closure

Решения, которые влияют на разработку, зафиксированы в
[docs/espo-dental-product-decisions.md](espo-dental-product-decisions.md).
Короткая выжимка:

- cabinet procedure requirements пока остаются JSON-backed, без новой сущности;
- patient flags пока покрываются `vip`, `restrictions`, questionnaire alerts и статусами;
- family MVP остается через parent/guardian and child links;
- patient portal использует OTP session как основной вход;
- cash shift остается отдельной операционной сущностью;
- reports развиваются от existing endpoints, dashlets and `ReportDefinition`;
- AI/MCP работает через narrow tools and proposal records, без прямых risky mutations.

### 3.3 End-to-end demo must become the product acceptance source

В SimpleStom была сильная идея: не показывать страницы, а показывать работу клиники. В EspoDental нужно закрепить такой же сценарий как главный критерий приемки.

## 4. Target Modules

### Dashboard

Role-specific operational action center.

For administrator:

- today schedule;
- waiting patients;
- unconfirmed appointments;
- reschedule requests;
- questionnaire alerts;
- failed notifications;
- low-stock alerts;
- quick action: create appointment.

For doctor:

- own today appointments;
- waiting patients;
- active visit shortcut;
- recent clinical tasks.

For manager:

- revenue and debt snapshot;
- doctor/cabinet utilization;
- low stock and expiry;
- payroll status;
- operational exceptions.

### Calendar And Booking

Calendar must be the main appointment workspace:

- day view by default;
- day/week/month;
- grouping by doctors or cabinets;
- visible doctor/cabinet/status/service filters;
- mini-calendar for fast date movement;
- right panel for waiting list, cancelled/no-show and reschedule requests;
- free-slot click opens booking wizard;
- drag/drop and resize preserve current backend conflict checks;
- clinic-local time everywhere in UI, UTC in storage.

Booking wizard:

- patient search by name, phone, card number;
- create preliminary patient inside modal;
- doctor, clinic, cabinet, service and duration;
- procedure/cabinet requirements;
- free slots only, with duration bounded by actual free interval;
- reminder/confirmation channel;
- no generic full EspoCRM form escape hatch for normal reception flow.

### Patients

Patient workspace should be the receptionist and doctor entry point:

- two-pane patient list and selected patient card;
- quick filters: active, preliminary, VIP, debt, overdue questionnaire, medical alerts, child;
- compact card: contacts, age, preferred channel, VIP, restrictions, balance, next appointment;
- tabs: basic, tooth chart, history, questionnaire, files, finance, orthodontics, family, CBCT/Orthanc;
- clear separation between clinical data and financial data;
- header action: book appointment;
- file upload action must respect file permissions and clinical context.

### Questionnaire And Portal

Questionnaire requirements:

- adult/child schema;
- RU/EN/ES UI language switching;
- ES/RU PDF output;
- all visible medical yes/no answers required;
- signature required;
- structured answers stored;
- medical alert answers produce patient warning;
- expiry after one year.

Patient portal:

- future appointments only;
- reschedule request without exposing occupied slots;
- same-doctor free slots by default;
- original appointment stays active until clinic approval;
- request and session events audited;
- phase 1 human administrator review;
- phase 2 assistant proposal/LLM only through controlled MCP/Integration contract.

### Visit Workspace

Doctor-facing workspace:

- doctor, assistant, patient and appointment context fixed from appointment;
- complaints and performed work with common and personal templates;
- autosave for clinical notes;
- services from category-first catalog;
- copied prices and discounts;
- material norms copied to visit material lines;
- actual material consumption editable only while allowed;
- tooth chart editable only during active visit;
- photos and files;
- treatment plan and PDF export;
- finish checklist.

Finish visit must remain atomic and idempotent:

- appointment status;
- invoice and invoice lines;
- stock movements;
- patient balance;
- read-only visit state.

### Services And Inventory

Services:

- category-first service catalog;
- duration, price, VAT, color;
- material norms;
- cabinet/procedure requirements;
- active/inactive status.

Inventory:

- warehouses for clinic and cabinets;
- stock lots with expiry;
- FEFO write-off;
- issue-to-cabinet workflow;
- low stock and expiry alerts;
- reorder URL;
- stock movements immutable after posting;
- inventory task generation by responsible role/user.

### Cash Desk

Cash desk is a separate operational workspace:

- unpaid/partially paid invoice list;
- filter by clinic, doctor, patient, period;
- selected invoice panel;
- payment wizard with cash/card/bank transfer/crypto/advance;
- prepayment without invoice as patient credit;
- apply advance to invoice;
- refunds as separate outbound payments;
- storno/write-off/complaint as correction records;
- invoice, act, receipt/factura printing;
- cash shift close/reconcile.

### Reports And Payroll

Reports:

- revenue;
- P&L;
- cash movement;
- service profitability;
- material finance;
- doctor utilization;
- cabinet utilization;
- primary patient funnel;
- no-show/cancellation;
- low stock/expiry;
- payroll.

Payroll:

- fixed salary;
- hourly;
- percent of paid work;
- completed case;
- no calculation;
- source breakdown for every amount;
- approval/pay/cancel statuses;
- immutable closed periods with corrections.

### Settings

Profile:

- phone, email, password, avatar, timezone, language, birth date;
- 2FA app/TOTP and future methods;
- notification preferences;
- personal visit note templates.

Clinic/system:

- clinics;
- cabinets and capabilities;
- users, teams/groups, roles and matrix ACL;
- doctor/assistant schedules and cleanup time;
- service/material dictionaries;
- payment methods;
- tooth chart dictionaries;
- SMTP, WhatsApp, Telegram;
- AI/MCP settings: server URL, model, transport, timeout, allowed tools, role access, logging, PHI storage policy, healthcheck, fallback.

## 5. Development Sequence

### Stage A - Product Lock

Goal: close the remaining product decisions before implementation.

Deliverables:

- decision log for cabinet capabilities, patient flags, family graph, portal auth, cash shift and report builder;
- updated acceptance demo script;
- updated docs linking SimpleStom reference to EspoDental module boundaries.

Done when:

- no open decision blocks calendar, patient workspace, cash desk or inventory work.

Status:

- product decisions are locked in `docs/espo-dental-product-decisions.md`;
- Stage B first operational language pass is complete for the active
  SimpleStom-style workspaces;
- Stage C calendar/booking has started with visible doctor/cabinet filters and
  a toggleable day-control panel.

### Stage B - UX Language And Visual Consistency

Goal: make current SimpleStom-style workspaces feel like one Russian dental CRM.

Deliverables:

- localized status/badge/action text;
- normalized button labels and empty states;
- removal of leftover generic actions from operational workspaces;
- visual audit screenshots for dashboard, calendar, patient workspace, visit, cash desk, inventory.

Done when:

- receptionist can understand the main workflow without seeing English/internal labels.

Status:

- centralized Russian labels and badges were added to the scoped SimpleStom UI
  helper;
- patient workspace rows/header, tooth chart values, questionnaire tables, slot
  booking candidates, cash desk actions, inventory levels and legacy calendar
  fallback text now use receptionist-facing Russian wording;
- manager report dashlets now use Russian loading, empty, error and table header
  text;
- focused automated coverage is in `tests/Phase50OperationalLanguageTest.php`.

### Stage C - Calendar And Slot Booking

Goal: make booking the strongest part of the product.

Deliverables:

- final calendar toolbar;
- mini-calendar and right panel toggle;
- doctor/cabinet/service filters;
- procedure requirement filtering;
- slot-first wizard polish;
- waitlist and cancelled/no-show panel behavior;
- reschedule request visibility.

Done when:

- admin can book a new patient from a free slot in one modal and conflicts are explained in human terms.

Status:

- first calendar-fidelity pass added visible doctor and cabinet filters to the
  feedback calendar dashlet and `Appointment` list workspace;
- the calendar and feedback-panel endpoints now accept matching doctor/cabinet
  filters;
- the right control panel can be hidden and switched between waitlist and
  cancelled/no-show rows;
- service/procedure requirement filtering now uses JSON-backed
  `Service.cabinetRequirements` to narrow compatible cabinets and validate slot
  booking;
- slot booking now receives the actual free window from the calendar grid,
  warns when a selected service duration does not fit and translates common
  booking conflicts into operational Russian messages;
- the right day-control panel now includes active reschedule requests as a
  first-class `Переносы` mode on both calendar surfaces;
- automated and browser verification passed for the service filter, booking
  payload, calendar render path and free-cell slot modal on 2026-05-26;
- remaining Stage C work is limited to larger calendar semantics such as the
  deferred month view and request approval/rejection actions.

### Stage D - Patient Workspace

Goal: make the patient card a real clinical/administrative workspace.

Deliverables:

- richer patient list rows;
- compact patient summary;
- alerts for questionnaire, restrictions, debt and VIP;
- tab polish for history, files, finance, family and tooth chart;
- direct links to source visit/questionnaire/invoice records.

Done when:

- admin and doctor can answer "who is this patient, what is next, what is risky, what is owed" from one workspace.

Status:

- first enrichment pass on 2026-05-27 added age, preferred communication
  channel, next appointment, VIP/restriction/questionnaire/debt alert badges
  and open invoice balance to the SimpleStom patient workspace endpoint and
  dashlet;
- patient workspace tabs now include direct links to source `Appointment`,
  `Visit`, `HealthQuestionnaire`, open `Invoice` and `Payment` records while
  preserving the clinical/finance separation;
- browser smoke confirmed the enriched patient workspace and tab source links
  render on the dashboard.

### Stage E - Questionnaire And Portal Hardening

Goal: close the legal and communication loop.

Deliverables:

- schema comparison against SimpleStom adult/child spec;
- final PDF print layout verification;
- portal future appointments and reschedule request flow;
- patient-safe free-slot output;
- portal session/event audit review.

Done when:

- questionnaire and reschedule can be demonstrated on tablet/mobile without internal data leaks.

### Stage F - Visit Workspace And Clinical Tools

Goal: make the doctor screen comfortable enough for daily use.

Deliverables:

- cleaner visit layout;
- personal template management in profile;
- tooth chart history preview inside patient workspace;
- treatment plan flow;
- post-finish file additions;
- finished visit lock/correction rules documented in UI.

Done when:

- doctor can start, document, finish and review a visit without switching through generic CRM screens.

### Stage G - Services And Inventory Workspace

Goal: turn stock from a report into an operating process.

Deliverables:

- service catalog with material norms and cabinet requirements;
- inventory workspace over warehouses/lots/cabinets;
- receipt, transfer, write-off and adjustment flows;
- expiry and reorder panels;
- inventory task generation.

Done when:

- material movement can be shown from purchase to cabinet issue to visit consumption to reorder alert.

### Stage H - Cash Desk And Finance

Goal: make the cash desk safe and fast.

Deliverables:

- selected invoice action panel;
- payment wizard;
- advance payment and application;
- refund/storno/write-off/complaint workflows;
- cash shift close/reconcile;
- printable invoice/act/receipt/factura review.

Done when:

- administrator can close the financial part after a visit and every correction is traceable.

Status:

- 2026-05-27 pass added selected-doctor filtering through the invoice-linked
  visit, selected-invoice action panel and a cash-desk payment wizard entry
  point that posts to `Payment/action/accept`;
- browser smoke confirmed the dashboard cash desk renders the doctor selector,
  selected invoice details and disabled payment state for already paid invoices;
- demo seed now includes a payable open invoice so the cash desk payment wizard
  can be accepted in browser smoke without depending on stale local data;
- browser smoke with the refreshed demo seed confirmed the payment wizard opens
  with the invoice balance and payment methods, then closes on cancel without
  posting a payment.

### Stage I - Reports, Payroll And Management

Goal: give the manager useful control without external spreadsheets.

Deliverables:

- saved report templates or builder improvements where current reports are insufficient;
- report export;
- payroll source explanation;
- manager dashboard alignment;
- report acceptance fixtures in demo seed.

Done when:

- manager can review clinic state, stock risks, payroll and profitability from structured data.

### Stage J - Integrations And Virtual Administrator

Goal: prepare automation without letting AI mutate risky data directly.

Deliverables:

- SMTP/Telegram/WhatsApp live acceptance once credentials exist;
- notification retry/failed dashboard;
- MCP healthcheck and tool audit;
- assistant proposals for reschedule and patient communication;
- human approval workflow for risky actions.

Done when:

- assistant can draft or propose actions, but medical/financial mutations remain controlled.

### Stage K - Demo And Release Readiness

Goal: make the product demonstrable and installable.

Deliverables:

- demo seed with clinics, cabinets, staff, schedules, patients, visits, invoices, stock and reports;
- browser demo script;
- Synology/Proxmox deployment verification;
- backup/restore check;
- acceptance checklist pass.

Done when:

- a new developer or clinic owner can run the demo and see a complete dental CRM flow.

## 6. Suggested Immediate Next Step

Continue Pass 4:

1. Build the inventory workspace over warehouses, lots,
   cabinet issue rows, expiry alerts and future-order candidates.
2. Keep the existing inventory report dashlet as a manager summary, not as the
   primary stock workspace.

Do not start with reports or AI. They are valuable, but the core product value is still the daily operational chain: call, book, remind, arrive, questionnaire, visit, invoice, payment, stock, next appointment.
