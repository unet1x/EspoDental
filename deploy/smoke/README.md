# EspoDental smoke test

Boots a disposable MariaDB + EspoCRM 9.2.7 stack with the module sources
mounted into the EspoCRM container, waits for the EspoCRM installer page to
return HTTP 200 and verifies that the module entry-points are visible
inside the container.

## Run

```bash
bash deploy/smoke/smoke.sh
```

Optional environment variables:

| Variable | Default | Meaning |
| --- | --- | --- |
| `SMOKE_PORT` | `18080` | Host port exposed by the smoke web container |
| `SMOKE_TIMEOUT` | `180` | Seconds to wait for HTTP 200 |

## What the script checks

1. `mariadb` becomes healthy.
2. `espocrm` serves HTTP 200 on `/`.
3. Inside the container these files exist:
   - `custom/Espo/Modules/EspoDental/Resources/metadata/scopes/Patient.json`
   - `custom/Espo/Modules/EspoDental/Resources/metadata/scopes/OrthodonticCard.json`
   - `custom/Espo/Modules/EspoDental/Resources/routes.json`
   - `client/custom/modules/espo-dental/src/lib/resource-grid.js`
4. `routes.json` is valid JSON.

If anything fails the script prints the last 50 lines of the web container
log and exits non-zero. The stack is always torn down (`down -v`) on exit.

## Notes

This is **not** an installation test — the EspoCRM installer is not
auto-completed. The script only proves that the module sources are
correctly laid out and visible to PHP. For a real install test, follow the
manual flow described in [docs/admin-guide.md](../../docs/admin-guide.md).
