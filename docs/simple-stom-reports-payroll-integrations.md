# SimpleStom Reports, Payroll And Integrations Contract

Last updated: 2026-05-27

Stage 12 reconciles SimpleStom management modules with the existing
EspoDental report, salary and messaging foundation.

## Saved Report Definitions

`ReportDefinition` stores SimpleStom-style saved report templates. Reports are
templates only: results are recalculated by the existing EspoDental report and
dashlet services instead of storing stale snapshots.

Supported sources:

- `payments`;
- `finance`;
- `service_profitability`;
- `material_finance`;
- `doctor_utilization`;
- `cabinet_utilization`;
- `patient_funnel`;
- `appointments`;
- `inventory`;
- `payroll`.

`WorkspaceSeeder` creates public starter templates for the SimpleStom demo
scope: revenue, P&L, service profitability, material finance, doctor/cabinet
utilization, patient funnel, appointments/no-shows, inventory/FEFO and payroll.

## Management Snapshot

Stage I adds a manager control surface on top of the existing report endpoints:

- endpoint: `GET /EspoDental/Report/managementSnapshot`;
- dashlet: `ManagementSnapshot`;
- manager dashboard placement: first surface after the action center.

Supported query parameters:

- `dateFrom`, `dateTo`: optional period bounds. When omitted, the endpoint uses
  the current calendar month.
- `clinicId`: optional clinic filter for payments, invoice risk, material cost,
  appointment quality, inventory status and cabinet utilization.
- `limit`: optional row limit for top lists; clamped by the service to a small
  manager-friendly range.

The snapshot combines revenue, open invoice balance, known material cost,
payroll accrual, gross after known costs, stock risk counts,
no-show/cancellation rates, top doctor productivity, top cabinet utilization
and payroll source rows. It deliberately reuses existing structured workflow
data instead of introducing a broad custom report builder before manager demo
feedback.

Response sections:

- `finance`: paid revenue, open invoice count/balance, overdue invoice count,
  known material cost, accrued payroll and gross after known costs.
- `appointmentQuality`: total appointments, no-shows, cancellations and issue
  rates for the selected period.
- `stock`: aggregate inventory risk and movement summary from
  `inventoryStatus`.
- `payroll`: salary entry totals by status plus the latest entry rows and their
  `sourceBreakdown`.
- `doctorRows`, `cabinetRows`, `stockRows`: compact drill-down rows reused from
  existing report services.

`materialCost` is intentionally conservative: it sums known outbound
non-transfer inventory movements in the selected period. It is not yet a full
clinic P&L cost model and should be treated as an operational margin signal.

## Report Export

Stage I adds a structured export endpoint without introducing a broad custom
report builder:

- endpoint: `GET /EspoDental/Report/export`;
- formats: `csv` and `json`;
- default source: `finance`;
- manager UI entry point: `ManagementSnapshot` dashlet button `Экспорт CSV`.

Supported query parameters:

- `source`: one of `payments`, `finance`, `service_profitability`,
  `material_finance`, `doctor_utilization`, `cabinet_utilization`,
  `patient_funnel`, `appointments`, `inventory` or `payroll`.
- `format`: `csv` or `json`; unsupported formats are rejected.
- `dateFrom`, `dateTo`, `clinicId`: same period/clinic filters used by the
  source report where applicable.
- `limit`: caps exported rows and is clamped by the service.

The response is JSON metadata plus file content:

- `filename`;
- `mimeType`;
- `columns`;
- `rows`;
- `content`.

CSV content is returned with a UTF-8 BOM and escaped cells so the client can
download it through a Blob without adding binary response handling to EspoCRM.
JSON export returns the same structured columns and rows in `content`.

Source mapping:

- `payments`: payment rows for the selected period.
- `finance`: management snapshot metrics and top doctor/cabinet/payroll rows.
- `service_profitability`: finished-visit service revenue, known material cost
  from linked visit material lines and resulting margin.
- `material_finance`: stock movements with quantity, unit price and total cost.
- `doctor_utilization`, `cabinet_utilization`, `appointments`, `inventory`:
  reuse the existing report endpoints.
- `patient_funnel`: preliminary-patient status counts, converted leads and
  active patient count.
- `payroll`: salary entries with source breakdown.

## Payroll Source Breakdown

`SalaryEntry` now has read-only `sourceBreakdown` JSON. `SalaryService::buildEntry`
fills it with:

- doctor reception revenue basis and completed visit count;
- assistant reception revenue basis;
- manual bonus and deduction adjustments from `SalaryBonus`;
- salary profile/rate rule used for the calculation.

This keeps EspoDental's existing `SalaryEntry`, `SalaryProfile` and
`SalaryBonus` model while satisfying SimpleStom's requirement that payroll
lines disclose their sources.

## Integration Settings And Secrets

`IntegrationSettings` stores clinic-level enablement and public configuration
for supported integration types:

- `smtp`;
- `whatsapp`;
- `telegram`.

`IntegrationSecret` stores named secret records for provider tokens, SMTP
passwords and API keys. The structural service
`IntegrationSettingsService::sanitizeSecret` exposes only metadata and
`valuePresent`; it never returns `secretValue`.

Module settings also expose SMTP fields next to the existing Telegram and
WhatsApp settings so a clinic can configure email delivery without adding
external calls to tests.

MCP and AI integration behavior remains explicitly out of scope for this
migration run.
