# Proxmox VM Migration Runbook

Last updated: 2026-05-22

This runbook covers the planned move from the current Synology/Docker target to
an AOOSTAR WTR MAX host running Proxmox with a dedicated EspoDental VM.

## 1. Target Topology

- Host: AOOSTAR WTR MAX.
- Hypervisor: Proxmox VE on bare metal.
- Workload: one dedicated Linux VM for EspoCRM/EspoDental.
- Runtime inside the VM: Docker Compose.
- Data principle: database, uploads and module sources live outside ephemeral
  containers on named host paths or mounted VM disks.
- Staging: either a second VM or the existing staging Compose stack inside the
  same VM until a separate staging VM is justified.

Recommended VM baseline:

- 4 vCPU;
- 8 GB RAM minimum, 12-16 GB if the same VM also runs staging;
- 120 GB system disk;
- separate data disk or dataset for `/srv/espodental`;
- daily Proxmox backup job to storage outside the host.

## 2. VM Directory Layout

Use one root so backup jobs and restore procedures are obvious:

```text
/srv/espodental/
  compose/
    docker-compose.yml
    .env
  module/
    EspoDental git checkout
  data/
    espocrm uploads/config/cache
  db/
    MariaDB data
  backups/
    db/
    uploads/
    module/
```

Production Compose should mount:

- `/srv/espodental/module` to the EspoCRM custom module path;
- `/srv/espodental/data` to EspoCRM persistent data;
- `/srv/espodental/db` to MariaDB persistent data.

## 3. Pre-Migration Backup On Synology

Stop write traffic if possible, then create a consistent source backup:

```bash
cd /volume1/docker/espodental
docker compose exec -T db mariadb-dump \
  --single-transaction --quick --routines --triggers --events \
  -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE" \
  | gzip -9 > /volume1/espodental-migration/db.sql.gz

rsync -aH --delete /volume2/espodental/data/ /volume1/espodental-migration/data/
rsync -aH --delete /volume1/espomodule/ /volume1/espodental-migration/module/
```

Record the source git revision:

```bash
git -C /volume1/espomodule rev-parse HEAD > /volume1/espodental-migration/module-revision.txt
```

## 4. Restore Into The Proxmox VM

Copy the backup into the VM:

```bash
rsync -aH /volume1/espodental-migration/ root@<vm-ip>:/srv/espodental/backups/import/
```

Prepare the VM paths and ownership:

```bash
sudo mkdir -p /srv/espodental/{compose,module,data,db,backups}
sudo chown -R 33:33 /srv/espodental/data
sudo chown -R 999:999 /srv/espodental/db
sudo chown -R "$USER":"$USER" /srv/espodental/module /srv/espodental/compose
```

Restore module sources:

```bash
rsync -aH --delete /srv/espodental/backups/import/module/ /srv/espodental/module/
```

Restore database after the Compose stack has created an empty database:

```bash
cd /srv/espodental/compose
docker compose up -d db
gunzip -c /srv/espodental/backups/import/db.sql.gz \
  | docker compose exec -T db mariadb \
      -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"
```

Restore uploads/data:

```bash
rsync -aH --delete /srv/espodental/backups/import/data/ /srv/espodental/data/
```

Start CRM and rebuild:

```bash
docker compose up -d
docker compose exec -T espocrm php rebuild.php
docker compose exec -T espocrm php command.php espo-dental-bootstrap
docker compose exec -T espocrm php command.php update-app-timestamp
```

## 5. Verification After Restore

Run these before changing DNS or clinic traffic:

```bash
docker compose ps
curl -fsS http://<vm-ip>:8080/
docker compose exec -T espocrm php rebuild.php
docker compose exec -T espocrm php command.php espo-dental-bootstrap
docker compose exec -T espocrm sh -lc "find custom/Espo/Modules/EspoDental -name '*.php' -print0 | xargs -0 -n1 php -l"
```

Application checks:

- login as admin;
- open patient list and one patient card;
- open resource calendar;
- open one uploaded visit photo or questionnaire PDF;
- create a staging-only test backup and restore it into staging;
- confirm no new critical errors in EspoCRM logs.

## 6. Ongoing Backup Policy

Use two layers:

1. Proxmox VM backup for disaster recovery.
2. Application-aware backup for portable restore.

Application-aware backup should run daily:

```bash
docker compose exec -T db mariadb-dump \
  --single-transaction --quick --routines --triggers --events \
  -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE" \
  | gzip -9 > /srv/espodental/backups/db/db-$(date +%F).sql.gz

rsync -aH --delete /srv/espodental/data/ /srv/espodental/backups/uploads/latest/
git -C /srv/espodental/module rev-parse HEAD \
  > /srv/espodental/backups/module/revision-$(date +%F).txt
```

Retention target:

- 14 daily database dumps;
- 8 weekly database dumps;
- daily Proxmox VM backup retained according to available external storage.

## 7. Rollback

Before DNS cutover, rollback is simply keeping Synology production untouched.

After cutover:

1. Put the VM CRM into maintenance mode or stop the web container.
2. Restore the latest known-good database dump.
3. Restore `/srv/espodental/data` from the matching uploads backup.
4. Checkout the matching module revision.
5. Run `rebuild.php`, `espo-dental-bootstrap` and `update-app-timestamp`.
6. Verify login, patient list, files and calendar before reopening access.

Never promote an unverified staging restore directly over production.
