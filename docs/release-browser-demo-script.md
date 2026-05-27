# EspoDental Release Browser Demo Script

Last updated: 2026-05-27

Use this after `bash deploy/local/release-readiness-smoke.sh` and
`bash deploy/check-deploy-readiness.sh`. The script is intentionally manual:
the release browser pass should confirm what a clinic owner or manager sees,
while risky state-changing actions stay closed or cancelled.

## Preconditions

- Local stack is available at <http://localhost:18080>.
- Login works with `admin / espodental-admin`.
- `docs/release-acceptance-checklist.md` automated gate is green.
- No live provider smoke is in scope unless credentials were accepted for a safe
  demo channel outside this script.

## Pass/Fail Evidence

Record:

- date and git commit;
- browser and viewport;
- smoke result;
- deploy-readiness result;
- one screenshot or note per section below;
- skipped provider-send reason.

## 1. Dashboard Entry

Open the dashboard.

Expected signals:

- action center is visible;
- calendar, patient workspace, cash desk and management surfaces load without
  route/controller errors;
- no visible raw internal labels block the primary workflow.

## 2. Calendar And Booking

Open Resource Calendar.

Expected signals:

- doctor/cabinet filters and mini-calendar are visible;
- waitlist, cancelled/no-show and reschedule modes show demo rows;
- clicking a free cell opens the slot-first booking modal;
- close the modal without saving if this is a release evidence pass.

## 3. Patient Workspace

Open the patient workspace and select `Смирнов Алексей`.

Expected signals:

- compact summary shows demographics, channel, next appointment and balance;
- questionnaire alerts and medical/restriction badges are visible when present;
- history, questionnaire, files, finance, family, tooth chart and CBCT/Orthanc
  sections show source links or empty states without raw JSON.

## 4. Cash Desk

Open Cash Desk.

Expected signals:

- doctor selector and invoice filters are visible;
- payable demo invoice can be selected;
- payment wizard opens with invoice balance and payment methods;
- cancel the wizard without posting payment.

## 5. Inventory

Open Inventory.

Expected signals:

- warehouses, selected stock lots, FEFO/expiry areas, cabinet issue rows and
  recent movements are visible;
- receipt, transfer, write-off and adjustment dialogs can open;
- close every dialog without saving.

## 6. Management And Reports

Open the manager dashboard.

Expected signals:

- `Управленческий срез` appears before report exports;
- finance, risks, doctors, cabinets and payroll panels show seeded data;
- `ReportExportCenter` can preview a source and download CSV/JSON;
- report downloads come from `EspoDental/Report/export`.

## 7. Integration Operations

Open or inspect `IntegrationOpsCenter` on the manager dashboard.

Expected signals:

- Stage J acceptance panel shows `stageJAcceptance`;
- MCP tools show zero direct mutation tools;
- provider readiness checklist does not expose secret values;
- queue preflight shows `queuedReadyCount` and `queuedBlockedCount`;
- failed notification and pending assistant proposal rows link to CRM records.

Do not run provider sends, `processQueue`, provider credential acceptance,
payment posting, visit finish or inventory write actions during this pass.

## 8. Final Decision

Accept only when:

- automated and deploy-readiness checks passed;
- every browser section above has a pass note or screenshot;
- skipped provider live smoke is explicitly recorded;
- no critical app log issue appeared during the pass.
