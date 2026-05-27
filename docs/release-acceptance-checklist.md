# EspoDental Release Acceptance Checklist

Last updated: 2026-05-27

Use this checklist after code review and before calling a local demo or release
candidate accepted. It complements `docs/acceptance-checklist.md` with a compact
Stage K pass.

## 1. Automated Gate

Run from the repository root:

```bash
bash deploy/local/release-readiness-smoke.sh
bash deploy/check-deploy-readiness.sh
vendor/bin/phpunit tests --no-coverage
git diff --check
```

Required result:

- release-readiness smoke passes after rebuild, `espo-dental-bootstrap` and
  `espo-dental-demo-seed`;
- healthcheck includes `stageJAcceptance` with no direct MCP mutation tools and
  no restricted MCP routes;
- management snapshot, finance JSON export and inventory workspace return
  seeded demo data;
- deploy-readiness check validates local/prod/staging compose config, backup
  script syntax and Synology/Proxmox recovery runbook coverage;
- PHPUnit and whitespace checks pass.

## 2. Browser Workspace Gate

Open <http://localhost:18080> as `admin / espodental-admin` after the automated
gate. Use `docs/release-browser-demo-script.md` for the detailed walkthrough.

Accept the browser pass only when:

- dashboard loads without visible route/controller errors;
- action center, calendar, cash desk, patient workspace, management snapshot,
  report export center and `IntegrationOpsCenter` are visible;
- Resource Calendar shows the waitlist/cancelled/reschedule side panel and can
  open the slot-first booking modal from a free cell;
- patient workspace for `Смирнов Алексей` shows demographics, questionnaire
  alerts, clinical history, tooth chart, files and finance sections;
- cash desk selects the payable demo invoice and opens the payment wizard; close
  it without posting payment;
- inventory workspace shows warehouses, FEFO lot, cabinet issue, recent
  movement rows and write-flow dialogs; close dialogs without saving;
- manager dashboard shows `stageJAcceptance`, provider readiness, queue
  preflight, failed notifications and pending assistant proposals.

## 3. API Gate

The release-readiness smoke is the canonical API pass. If checking manually,
use only safe endpoints:

- `GET /EspoDental/Integration/healthcheck?limit=2`;
- `GET /EspoDental/Report/managementSnapshot?limit=2`;
- `GET /EspoDental/Report/export?source=finance&format=json&limit=2`;
- `GET /EspoDental/Inventory/workspace?limit=2`.

Do not include these actions in automated release smoke:

- live SMTP, Telegram or WhatsApp sends;
- `NotificationLog/processQueue`;
- `Integration/acceptProviderCredentials`;
- payment posting;
- visit finish;
- inventory receipt, transfer, write-off or adjustment.

## 4. Install And Recovery Gate

Before a deployable release:

- `docker compose -f deploy/local/docker-compose.yml config --quiet` passes;
- `docker compose --env-file deploy/.env.example -f deploy/docker-compose.yml config --quiet` passes;
- `docker compose --env-file deploy/staging/.env.example -f deploy/staging/docker-compose.yml config --quiet` passes;
- `bash deploy/check-deploy-readiness.sh` passes;
- Synology install notes are current for rebuild/bootstrap;
- Proxmox VM restore runbook is current for database dump, uploads restore,
  module revision, rebuild/bootstrap, verification and rollback;
- backup and restore scripts are reviewed for the target environment.

## 5. Evidence

Record the release candidate with:

- git commit hash;
- release-readiness smoke result;
- PHPUnit result;
- browser workspace pass date;
- deploy config check result;
- any known manual-only provider smoke that was deliberately skipped.
