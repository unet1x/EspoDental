#!/usr/bin/env bash
#
# EspoDental backup script for Synology DSM.
# Dumps MariaDB and archives /var/www/html/data folder.
#
# Usage:
#   sudo /volume1/docker/espodental/backup.sh
#
# Recommended: schedule via DSM Task Scheduler, daily at 03:00.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/.env"

if [[ ! -f "${ENV_FILE}" ]]; then
    echo "Error: ${ENV_FILE} not found" >&2
    exit 1
fi

set -a
# shellcheck disable=SC1090
source "${ENV_FILE}"
set +a

BACKUP_DIR="${BACKUP_DIR:-/volume2/espodental/data/backups}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"
TS="$(date +%Y%m%d-%H%M%S)"

mkdir -p "${BACKUP_DIR}"

DB_FILE="${BACKUP_DIR}/db-${TS}.sql.gz"
DATA_FILE="${BACKUP_DIR}/data-${TS}.tar.gz"

echo "[1/3] Dumping database to ${DB_FILE}"
docker exec -e MYSQL_PWD="${MARIADB_ROOT_PASSWORD}" espodental-db \
    mariadb-dump \
        --single-transaction \
        --quick \
        --routines \
        --triggers \
        --events \
        -u root \
        "${ESPOCRM_DATABASE_NAME}" \
    | gzip -c > "${DB_FILE}"

echo "[2/3] Archiving data directory to ${DATA_FILE}"
tar --warning=no-file-changed -czf "${DATA_FILE}" \
    --exclude='backups' \
    -C /volume2/espodental data

echo "[3/3] Pruning backups older than ${RETENTION_DAYS} days"
find "${BACKUP_DIR}" -maxdepth 1 -type f -name '*.gz' -mtime +"${RETENTION_DAYS}" -print -delete || true

echo "Backup completed: ${DB_FILE}, ${DATA_FILE}"
