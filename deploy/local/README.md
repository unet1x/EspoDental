# Local Docker stack

This stack is for workstation development. It runs MariaDB, EspoCRM, the
EspoCRM daemon and websocket service with EspoDental sources bind-mounted from
this repository.

## Start

From the repository root:

```bash
docker compose -f deploy/local/docker-compose.yml up -d
```

Open <http://localhost:18080>. Default development credentials:

- Username: `admin`
- Password: `espodental-admin`

After the first boot, register module metadata and seed roles:

```bash
docker compose -f deploy/local/docker-compose.yml exec espocrm php rebuild.php
docker compose -f deploy/local/docker-compose.yml exec espocrm php command.php espo-dental-bootstrap
```

## Stop

```bash
docker compose -f deploy/local/docker-compose.yml down
```

To reset the local database and EspoCRM data volume:

```bash
docker compose -f deploy/local/docker-compose.yml down -v
```

## Configuration

You can override defaults with environment variables:

- `ESPOCRM_HTTP_PORT` (default `18080`)
- `ESPOCRM_WS_PORT` (default `18081`)
- `ESPOCRM_ADMIN_USERNAME` (default `admin`)
- `ESPOCRM_ADMIN_PASSWORD` (default `espodental-admin`)
- `TZ` (default `Europe/Madrid`)
