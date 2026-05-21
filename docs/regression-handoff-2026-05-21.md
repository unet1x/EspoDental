# EspoDental Regression Handoff - 2026-05-21

This document captures regressions and high-risk inconsistencies found while
reviewing the `feature-front-desk-intake` branch on 2026-05-21. It is intended
for a fresh development chat with no prior context.

## Start Here

- Repository root: `/Users/unet1x/Codex/EspoDental`.
- Handoff branch: `regression-handoff-2026-05-21`.
- Source branch reviewed: `feature-front-desk-intake`.
- Local app URL from docs: `http://localhost:18080`.
- Local credentials used in existing docs: `admin` / `espodental-admin`.

Before fixing, read:

- `docs/product-spec.md`
- `docs/current-state.md`
- this file
- `docs/acceptance-checklist.md`
- `docs/dev-runbook.md`

## Verified During Review

The git worktree was clean before this documentation branch was created.

Passing checks:

```bash
node -e 'const fs=require("fs"); const path=require("path"); const roots=["src","tests"]; const files=[]; function walk(d){ for (const e of fs.readdirSync(d,{withFileTypes:true})) { const p=path.join(d,e.name); if (e.isDirectory()) walk(p); else if (p.endsWith(".json")) files.push(p); }} for (const r of roots) if (fs.existsSync(r)) walk(r); for (const f of files) JSON.parse(fs.readFileSync(f,"utf8")); console.log(`JSON OK ${files.length}`);'
docker compose -f deploy/local/docker-compose.yml exec -T espocrm php rebuild.php
docker compose -f deploy/local/docker-compose.yml exec -T espocrm find custom/Espo/Modules/EspoDental -name '*.php' -exec php -l {} +
```

Results:

- JSON metadata parsed: 331 files.
- `rebuild.php` completed without output/errors.
- PHP syntax check passed for module PHP files inside the local EspoCRM
  container.

Limitations:

- Host has no `php` or `composer`.
- The local EspoCRM container did not have `vendor/bin/phpunit`, so PHPUnit was
  not run.

## Regression 1 - Resource Calendar Timezone Drift

Product requirement:

- Booking feedback, slot labels and appointment display names must use the
  clinic timezone.
- Stored `Appointment.dateStart/dateEnd` must remain UTC.

Current evidence:

- `EspoDental/Calendar/freeSlots` returns both UTC and clinic-local values:
  `start`, `end`, `localStart`, `localEnd`, `timezone`.
- `EspoDental/Calendar/appointments` returns only raw `dateStart/dateEnd`.
- `resource-grid.js` parses `dateStart/dateEnd` as browser-local time and uses
  those parsed values for rendering, drag, resize and cell-click creation.

Files:

- `src/files/custom/Espo/Modules/EspoDental/Services/CalendarService.php`
  - `getDayData` builds appointment payload at lines around 93-105.
  - `findFreeSlots` already returns `localStart/localEnd` at lines around
    248-256.
  - `moveAppointment` accepts date strings and saves them directly at lines
    around 317-352.
- `src/files/client/custom/modules/espo-dental/src/lib/resource-grid.js`
  - `parseIso` creates browser-local `Date` objects from stored strings.
  - appointment cards render from `a.dateStart/a.dateEnd`.
  - drag/drop and resize send formatted local strings back to the server.
- `src/files/client/custom/modules/espo-dental/src/views/dashlets/resource-calendar.js`
  - move/resize calls `EspoDental/Calendar/move`.

API evidence from local stack:

```bash
curl -fsS -u admin:espodental-admin 'http://localhost:18080/api/v1/EspoDental/Calendar/freeSlots?dateFrom=2026-05-22&dateTo=2026-05-22&durationMinutes=30&limit=3'
```

Observed shape:

```json
{
  "start": "2026-05-22 05:00:00",
  "end": "2026-05-22 05:30:00",
  "localStart": "2026-05-22 08:00:00",
  "localEnd": "2026-05-22 08:30:00",
  "timezone": "Europe/Moscow"
}
```

But `appointments` for an existing record returned `dateStart:
"2026-05-15 13:30:00"` while the generated appointment name was
`"2026-05-15 16:30 - Завершено"`. This strongly indicates that the dashboard
calendar can render the same appointment 3 hours earlier than the clinic-local
name.

Expected fix:

- Define a single API contract for the resource calendar:
  - UTC fields for storage and persistence.
  - clinic-local fields for display and user interaction.
- Add `localStart`, `localEnd` and `timezone` to `getDayData` appointment
  payloads.
- Make `resource-grid.js` render from local fields.
- Make drag/drop, resize and empty-cell create persist UTC, or send explicit
  clinic-local values with timezone and convert server-side before saving.
- Add a regression test that proves a `13:30 UTC` appointment in
  `Europe/Moscow` renders as `16:30` and that a drag/drop save does not shift
  the persisted UTC value unexpectedly.

## Regression 2 - Global Appointment Quick-Create Is Incomplete

The patient header action `Записать на прием` passes `parentType=Patient` and
`parentId`, so that contextual booking path is still coherent.

The global quick-create path is risky:

- `WorkspaceSeeder` still includes `Appointment` in `quickCreateList`.
- `Appointment` modal is globally forced to `fullFormDisabled`.
- `Appointment/editSmall.json` hides `parent`.
- `Appointment.parent` is required in entity metadata.

Files:

- `src/files/custom/Espo/Modules/EspoDental/Tools/Installer/WorkspaceSeeder.php`
  - `quickCreateList` includes `Appointment`.
- `src/files/custom/Espo/Modules/EspoDental/Resources/metadata/clientDefs/Appointment.json`
  - all appointment edit modals use `espo-dental:views/appointment/modals/edit`.
- `src/files/client/custom/modules/espo-dental/src/views/appointment/modals/edit.js`
  - `fullFormDisabled: true`.
- `src/files/custom/Espo/Modules/EspoDental/Resources/layouts/Appointment/editSmall.json`
  - no `parent` row.
- `src/files/custom/Espo/Modules/EspoDental/Resources/metadata/entityDefs/Appointment.json`
  - `parent` is required.

Expected fix options:

- Remove `Appointment` from global `quickCreateList` and route normal booking
  through patient/preliminary-patient contextual actions only.
- Or keep global create, but use a different layout/modal there that includes a
  required patient/preliminary-patient picker.

Add an acceptance/browser check for both paths:

- Patient header booking remains short and prelinked.
- Global appointment quick-create is either unavailable or includes a required
  parent selector and saves successfully.

## Regression 3 - `finishVisit` Is Not Atomic Or Idempotent

Product/roadmap expectation:

- Finishing the visit should be one atomic clinical-financial operation.
- Repeated finish action should be idempotent.

Current flow in `VisitService::finishVisit`:

1. Recalculate visit total.
2. Set `Visit.status = finished`.
3. Set linked `Appointment.status = finished`.
4. Build invoice if service lines exist.
5. Consume stock.

If invoice creation or stock consumption fails after step 2 or 3, the database
can contain a finished visit without the downstream records. A second call then
throws `Visit is not in progress`, so it cannot repair or complete the missing
invoice/stock work.

Files:

- `src/files/custom/Espo/Modules/EspoDental/Services/VisitService.php`
  - status is saved before invoice/stock work.
- `src/files/custom/Espo/Modules/EspoDental/Services/InvoiceService.php`
  - `buildFromVisit` is mostly idempotent for invoices.
- `src/files/custom/Espo/Modules/EspoDental/Services/StockService.php`
  - prepared material consumption checks existing stock movements by
    `sourceVisitMaterialLineId`.

Expected fix:

- Wrap finish operation in an EspoCRM/DB transaction if available.
- Or reorder so status changes happen after invoice and stock creation.
- Make `finishVisit` idempotent for already-finished visits:
  - return the existing invoice if present;
  - create missing stock movements if absent;
  - do not duplicate invoice lines or stock movements.
- Add a test or API smoke path that calls finish twice and expects the same
  downstream records, not a conflict or duplicates.

## Regression 4 - Payment Server-Side Guards Are Weaker Than UI

The invoice payment UI blocks non-payable statuses, but `PaymentService::accept`
does not enforce the same rule server-side.

Files:

- `src/files/client/custom/modules/espo-dental/src/handlers/invoice/accept-payment.js`
  - blocks `storno`, `cancelled`, `draft`, `paid` in the browser.
- `src/files/custom/Espo/Modules/EspoDental/Services/PaymentService.php`
  - creates a completed inbound payment after checking only patient, amount,
    clinic and method.
- `src/files/custom/Espo/Modules/EspoDental/Controllers/Payment.php`
  - API action calls `PaymentService::accept`.

Expected fix:

- If `invoiceId` is provided, load the invoice server-side and reject payments
  against `draft`, `paid`, `storno` and `cancelled` invoices unless a documented
  correction or prepayment path is being used.
- Verify `patientId` matches the invoice patient.
- Verify `clinicId` matches the invoice clinic or derive it from the invoice.
- Decide whether overpayment is allowed. If yes, document how the surplus
  becomes patient credit. If no, reject `amount > invoice.balance`.
- Add API tests or smoke checks for draft, paid, cancelled/storno and patient
  mismatch cases.

## Regression 5 - Appointment Status Naming Drift

Docs and code do not use the same final appointment status name:

- Product spec and acceptance checklist say the appointment becomes
  `completed`.
- Code uses `finished`.
- `resource-grid.js` has a color for `completed` but not for `finished`, so
  finished appointments fall back to the default color.

Files:

- `docs/product-spec.md`
- `docs/acceptance-checklist.md`
- `docs/roadmap.md`
- `src/files/custom/Espo/Modules/EspoDental/Entities/Appointment.php`
- `src/files/custom/Espo/Modules/EspoDental/Resources/metadata/entityDefs/Appointment.json`
- `src/files/client/custom/modules/espo-dental/src/lib/resource-grid.js`

Expected fix:

- Choose one final status value.
- If keeping `finished`, update docs/acceptance text and add `finished` color
  handling in the resource grid.
- If migrating to `completed`, update entity options, constants, hooks,
  services, locales and migration/backfill logic.

## Test Coverage Gaps

Existing PHPUnit tests are mostly metadata/string checks. They did not catch
the regressions above.

Add coverage for:

- `CalendarService::getDayData` appointment payload includes clinic-local
  fields and `resource-grid` uses them.
- Drag/drop or resize does not shift UTC storage.
- Global appointment quick-create is either disabled or has a required parent
  selector.
- `finishVisit` is atomic/idempotent.
- `PaymentService::accept` rejects invalid invoice states and mismatched
  patient/clinic payloads.
- Appointment final status naming is consistent across docs, metadata, code
  and grid colors.

## Suggested Fix Order

1. Fix resource calendar timezone contract first. It is the most likely visible
   regression from the latest clinic-local booking changes.
2. Fix or remove global Appointment quick-create.
3. Harden `finishVisit` atomicity/idempotency.
4. Move payment status validation from UI-only into `PaymentService`.
5. Align appointment final status naming and grid colors.
6. Update acceptance checklist after code behavior is chosen.

## Useful Verification Commands

```bash
git status --short
node -e 'const fs=require("fs"); const path=require("path"); const roots=["src","tests"]; const files=[]; function walk(d){ for (const e of fs.readdirSync(d,{withFileTypes:true})) { const p=path.join(d,e.name); if (e.isDirectory()) walk(p); else if (p.endsWith(".json")) files.push(p); }} for (const r of roots) if (fs.existsSync(r)) walk(r); for (const f of files) JSON.parse(fs.readFileSync(f,"utf8")); console.log(`JSON OK ${files.length}`);'
docker compose -f deploy/local/docker-compose.yml exec -T espocrm php rebuild.php
docker compose -f deploy/local/docker-compose.yml exec -T espocrm find custom/Espo/Modules/EspoDental -name '*.php' -exec php -l {} +
curl -fsS -u admin:espodental-admin 'http://localhost:18080/api/v1/EspoDental/Calendar/appointments?date=2026-05-15'
curl -fsS -u admin:espodental-admin 'http://localhost:18080/api/v1/EspoDental/Calendar/freeSlots?dateFrom=2026-05-22&dateTo=2026-05-22&durationMinutes=30&limit=3'
```
