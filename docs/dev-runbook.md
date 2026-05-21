# EspoDental Developer Runbook

Last updated: 2026-05-14

This is the short operational guide for continuing development from a fresh
chat/session.

## 1. Read First

Before changing code, read:

- `docs/product-spec.md`
- `docs/current-state.md`
- `docs/regression-handoff-2026-05-21.md` if working on front-desk, booking,
  visit finish, payment or calendar regressions
- `docs/roadmap.md`
- `docs/acceptance-checklist.md`
- `docs/dev-runbook.md`

## 2. Local Docker

Start local EspoCRM:

```bash
docker compose -f deploy/local/docker-compose.yml up -d
```

Open:

```text
http://localhost:18080
```

Local credentials used during development:

```text
admin / espodental-admin
```

## 3. Rebuild And Bootstrap

After metadata, PHP or layout changes:

```bash
docker compose -f deploy/local/docker-compose.yml exec -T espocrm php rebuild.php
docker compose -f deploy/local/docker-compose.yml exec -T espocrm php command.php espo-dental-bootstrap
```

The bootstrap command is idempotent.

## 4. Fast Verification

PHP syntax:

```bash
docker compose -f deploy/local/docker-compose.yml exec -T espocrm sh -lc "find custom/Espo/Modules/EspoDental -name '*.php' -print0 | xargs -0 -n1 php -l"
```

JSON parse from host:

```bash
node -e "const fs=require('fs');const cp=require('child_process');const files=cp.execSync('rg --files src | rg \"\\\\.json$\"').toString().trim().split(/\\n/).filter(Boolean); for (const f of files) JSON.parse(fs.readFileSync(f,'utf8')); console.log('JSON OK', files.length);"
```

Smoke test:

```bash
SMOKE_PORT=18082 bash deploy/smoke/smoke.sh
```

Compose config:

```bash
docker compose -f deploy/local/docker-compose.yml config --quiet
docker compose --env-file deploy/.env.example -f deploy/docker-compose.yml config --quiet
docker compose --env-file deploy/staging/.env.example -f deploy/staging/docker-compose.yml config --quiet
```

Calendar API:

```bash
curl -fsS -u admin:espodental-admin "http://localhost:18080/api/v1/EspoDental/Calendar/appointments?date=2026-05-14&view=day"
```

Revenue report API:

```bash
curl -fsS -u admin:espodental-admin "http://localhost:18080/api/v1/EspoDental/Report/monthlyRevenue?monthsBack=3"
```

## 5. Development Discipline

- Work by roadmap phases.
- Keep each phase a connected user flow.
- Update docs when behavior changes.
- Add/adjust acceptance checks before declaring a phase complete.
- Commit each accepted slice separately.
- Do not rely on chat history as the only source of truth.

## 6. Git Handoff

Before pushing:

```bash
git status --short
git diff --stat
```

Suggested commit style:

```bash
git add README.md docs deploy src tests
git commit -m "Document EspoDental product roadmap"
git push origin main
```
