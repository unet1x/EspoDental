#!/usr/bin/env bash
#
# EspoDental — prod backup.
#
# Runs mysqldump against the prod MariaDB container, gzips the result with a
# datestamp into BACKUP_DIR, optionally tars /var/www/html/data/upload and
# prunes anything older than BACKUP_RETENTION_DAYS.
#
# Designed to be called by deploy/scripts/nightly.sh, but works stand-alone.
#
# Exit codes:
#   0  ok
#   2  config error / dump failure
#
# Env (defaults shown):
#   PROD_COMPOSE_DIR=/volume1/docker/espodental
#   PROD_COMPOSE_FILE=docker-compose.yml
#   PROD_ENV_FILE=${PROD_COMPOSE_DIR}/.env       # loaded for DB creds
#   BACKUP_DIR=/volume2/espodental/backups
#   BACKUP_RETENTION_DAYS=14
#   INCLUDE_UPLOADS=1                            # 1 -> tar data/upload too

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "${SCRIPT_DIR}/lib/common.sh"

PROD_COMPOSE_DIR="${PROD_COMPOSE_DIR:-/volume1/docker/espodental}"
PROD_COMPOSE_FILE="${PROD_COMPOSE_FILE:-docker-compose.yml}"
PROD_ENV_FILE="${PROD_ENV_FILE:-${PROD_COMPOSE_DIR}/.env}"
BACKUP_DIR="${BACKUP_DIR:-/volume2/espodental/backups}"
BACKUP_RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-14}"
INCLUDE_UPLOADS="${INCLUDE_UPLOADS:-1}"
PROD_UPLOAD_PATH="${PROD_UPLOAD_PATH:-/volume2/espodental/data}"

main() {
    log_info "backup-prod: start"

    load_env "${PROD_ENV_FILE}"
    require_env ESPOCRM_DATABASE_NAME ESPOCRM_DATABASE_USER ESPOCRM_DATABASE_PASSWORD

    mkdir -p "${BACKUP_DIR}"
    local stamp
    stamp="$(date '+%Y%m%d-%H%M%S')"
    local dump_path="${BACKUP_DIR}/db-${stamp}.sql.gz"
    local files_path="${BACKUP_DIR}/files-${stamp}.tar.gz"
    local manifest_path="${BACKUP_DIR}/manifest-${stamp}.txt"

    log_info "backup-prod: dumping database to ${dump_path}"
    if ! ( cd "${PROD_COMPOSE_DIR}" && \
        docker compose -f "${PROD_COMPOSE_FILE}" exec -T mariadb \
            mariadb-dump \
                --single-transaction --quick --routines --triggers --events \
                -u "${ESPOCRM_DATABASE_USER}" \
                "-p${ESPOCRM_DATABASE_PASSWORD}" \
                "${ESPOCRM_DATABASE_NAME}" \
        | gzip -c > "${dump_path}.tmp" ); then
        log_error "backup-prod: mariadb-dump failed"
        rm -f "${dump_path}.tmp"
        return 2
    fi
    mv "${dump_path}.tmp" "${dump_path}"

    local dump_size
    dump_size="$(stat -c %s "${dump_path}" 2>/dev/null || wc -c < "${dump_path}")"
    log_info "backup-prod: db dump ok (${dump_size} bytes)"

    if [ "${INCLUDE_UPLOADS}" = "1" ] && [ -d "${PROD_UPLOAD_PATH}/upload" ]; then
        log_info "backup-prod: archiving uploads to ${files_path}"
        if ! tar -C "${PROD_UPLOAD_PATH}" -czf "${files_path}.tmp" upload; then
            log_error "backup-prod: tar uploads failed"
            rm -f "${files_path}.tmp"
            return 2
        fi
        mv "${files_path}.tmp" "${files_path}"
    fi

    {
        printf 'pipeline_id=%s\n' "${ESPODENTAL_PIPELINE_ID}"
        printf 'timestamp=%s\n' "${stamp}"
        printf 'db_dump=%s\n' "${dump_path}"
        printf 'db_dump_size=%s\n' "${dump_size}"
        [ -f "${files_path}" ] && printf 'files_archive=%s\n' "${files_path}"
        printf 'db_name=%s\n' "${ESPOCRM_DATABASE_NAME}"
    } > "${manifest_path}"

    log_info "backup-prod: pruning backups older than ${BACKUP_RETENTION_DAYS} days"
    find "${BACKUP_DIR}" -maxdepth 1 -type f \
        \( -name 'db-*.sql.gz' -o -name 'files-*.tar.gz' -o -name 'manifest-*.txt' \) \
        -mtime "+${BACKUP_RETENTION_DAYS}" -print -delete | while read -r removed; do
            log_info "backup-prod: pruned ${removed}"
        done

    # Symlink for the latest dump so restore-to-staging always finds it
    ln -sfn "${dump_path}" "${BACKUP_DIR}/db-latest.sql.gz"
    [ -f "${files_path}" ] && ln -sfn "${files_path}" "${BACKUP_DIR}/files-latest.tar.gz"
    ln -sfn "${manifest_path}" "${BACKUP_DIR}/manifest-latest.txt"

    log_info "backup-prod: done"
    return 0
}

main "$@"
