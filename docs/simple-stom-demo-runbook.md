# SimpleStom Demo Environment Runbook

Last updated: 2026-05-26

Stage 13 provides a local demo database for reviewing the migrated
SimpleStom-style workflows inside EspoDental.

## Start Local Stack

```bash
docker compose -f deploy/local/docker-compose.yml up -d
docker compose -f deploy/local/docker-compose.yml exec -T espocrm php rebuild.php
docker compose -f deploy/local/docker-compose.yml exec -T espocrm php command.php espo-dental-bootstrap
docker compose -f deploy/local/docker-compose.yml exec -T espocrm php command.php espo-dental-demo-seed
```

Open <http://localhost:18080>.

Development credentials:

```text
admin / espodental-admin
```

The demo command is idempotent. It matches records by stable `DEMO SimpleStom`
markers and can be re-run after rebuilds.

## Demo Data

`espo-dental-demo-seed` creates:

- demo staff users for manager, administrator, doctor, assistant and stock
  manager;
- doctor shifts for today and tomorrow;
- an adult patient, a linked child patient and a preliminary patient;
- planned, finished, cancelled and preliminary-patient appointments;
- urgent waitlist entry and a manual reschedule proposal;
- health questionnaire, questionnaire token, portal session, portal event and
  pending reschedule request;
- finished visit with service line, material consumption and tooth-chart
  snapshot;
- invoice, card payment, advance payment and closed cash shift;
- FEFO lot, warehouse/cabinet stock movement, visit consumption and low-stock
  alert;
- payroll profile, manual bonus and salary entry with `sourceBreakdown`;
- disabled SMTP, WhatsApp and Telegram integration settings with demo secret
  metadata only.

External network calls are not made by the demo seed. MCP and AI behavior stay
out of scope for this migration run.

## Manual Demo Script

1. Open the dashboard as `admin` and review action center, calendar, cash desk,
   patient workspace and management dashlets.
2. Open Resource Calendar and confirm the waitlist/cancelled side panel has demo
   data.
3. Create a new appointment from a free slot using the preliminary patient flow.
4. Open the patient workspace for `Смирнов Алексей` and review demographics,
   questionnaire flags, tooth chart, clinical history and finance.
5. Open the future appointment and review the pending portal reschedule request.
6. Open the finished visit, service/material lines and tooth-chart snapshot.
7. Open Cash Desk, review the invoice-first payment data and closed shift.
8. Open Inventory, review the FEFO lot, cabinet issue, consumption and low-stock
   alert.
9. Open Report Definitions and Payroll, then inspect the salary entry source
   breakdown.

## Reset

```bash
docker compose -f deploy/local/docker-compose.yml down -v
docker compose -f deploy/local/docker-compose.yml up -d
```

Then rerun rebuild, bootstrap and demo seed.
