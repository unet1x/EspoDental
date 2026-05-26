# SimpleStom Reports, Payroll And Integrations Contract

Last updated: 2026-05-26

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
