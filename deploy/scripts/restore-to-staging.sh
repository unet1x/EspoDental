#!/usr/bin/env bash
#
# EspoDental — restore prod backup into staging.
#
# Drops & recreates the staging database, imports the latest gzipped dump,
# rsyncs uploads, restarts the staging Espo containers and runs a sanity
# check (HTTP 200 + a non-empty patient table).
#
# Exit codes:
#   0  ok
#   2  config error
#   3  import failed
#   4  sanity check failed

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "${SCRIPT_DIR}/lib/common.sh"

STAGING_COMPOSE_DIR="${STAGING_COMPOSE_DIR:-/volume1/docker/espodental-staging}"
STAGING_COMPOSE_FILE="${STAGING_COMPOSE_FILE:-docker-compose.yml}"
STAGING_ENV_FILE="${STAGING_ENV_FILE:-${STAGING_COMPOSE_DIR}/.env}"
STAGING_DATA_PATH="${STAGING_DATA_PATH:-/volume2/espodental-staging/data}"

PROD_UPLOAD_PATH="${PROD_UPLOAD_PATH:-/volume2/espodental/data}"
BACKUP_DIR="${BACKUP_DIR:-/volume2/espodental/backups}"
DUMP_PATH="${DUMP_PATH:-${BACKUP_DIR}/db-latest.sql.gz}"

STAGING_HEALTH_URL="${STAGING_HEALTH_URL:-http://localhost:8090/}"
STAGING_HEALTH_TIMEOUT_SEC="${STAGING_HEALTH_TIMEOUT_SEC:-180}"
PATIENT_TABLE_NAME="${PATIENT_TABLE_NAME:-patient}"

main() {
    log_info "restore-to-staging: start"

    load_env "${STAGING_ENV_FILE}"
    require_env ESPOCRM_DATABASE_NAME ESPOCRM_DATABASE_USER \
                ESPOCRM_DATABASE_PASSWORD MARIADB_ROOT_PASSWORD

    if [ ! -f "${DUMP_PATH}" ]; then
        log_error "restore-to-staging: dump not found at ${DUMP_PATH}"
        return 2
    fi

    log_info "restore-to-staging: stopping staging web tier (keeping mariadb up)"
    ( cd "${STAGING_COMPOSE_DIR}" && \
        docker compose -f "${STAGING_COMPOSE_FILE}" stop \
            espocrm espocrm-daemon espocrm-websocket >/dev/null )

    log_info "restore-to-staging: recreating database ${ESPOCRM_DATABASE_NAME}"
    if ! ( cd "${STAGING_COMPOSE_DIR}" && \
        docker compose -f "${STAGING_COMPOSE_FILE}" exec -T mariadb \
            mariadb -uroot "-p${MARIADB_ROOT_PASSWORD}" -e "\
                DROP DATABASE IF EXISTS \`${ESPOCRM_DATABASE_NAME}\`; \
                CREATE DATABASE \`${ESPOCRM_DATABASE_NAME}\` \
                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; \
                GRANT ALL PRIVILEGES ON \`${ESPOCRM_DATABASE_NAME}\`.* \
                    TO '${ESPOCRM_DATABASE_USER}'@'%'; \
                FLUSH PRIVILEGES;" >/dev/null ); then
        log_error "restore-to-staging: failed to recreate database"
        return 3
    fi

    log_info "restore-to-staging: importing dump (this can take minutes)"
    if ! ( cd "${STAGING_COMPOSE_DIR}" && \
        gunzip -c "${DUMP_PATH}" | \
        docker compose -f "${STAGING_COMPOSE_FILE}" exec -T mariadb \
            mariadb -uroot "-p${MARIADB_ROOT_PASSWORD}" \
            "${ESPOCRM_DATABASE_NAME}" >/dev/null ); then
        log_error "restore-to-staging: dump import failed"
        return 3
    fi

    if [ -d "${PROD_UPLOAD_PATH}/upload" ]; then
        log_info "restore-to-staging: rsync uploads prod -> staging"
        if ! rsync -a --delete \
            "${PROD_UPLOAD_PATH}/upload/" \
            "${STAGING_DATA_PATH}/upload/"; then
            log_error "restore-to-staging: rsync uploads failed"
            return 3
        fi
    fi

    log_info "restore-to-staging: starting staging web tier"
    ( cd "${STAGING_COMPOSE_DIR}" && \
        docker compose -f "${STAGING_COMPOSE_FILE}" up -d >/dev/null )

    log_info "restore-to-staging: clearing Espo cache & rebuilding"
    ( cd "${STAGING_COMPOSE_DIR}" && \
        docker compose -f "${STAGING_COMPOSE_FILE}" exec -T espocrm \
            bash -c 'rm -rf data/cache/* && php rebuild.php' >/dev/null ) || \
        log_warn "restore-to-staging: rebuild.php returned non-zero (may be OK)"

    sanity_check
}

sanity_check() {
    log_info "restore-to-staging: sanity check — waiting for ${STAGING_HEALTH_URL}"
    local deadline
    deadline=$(( $(date +%s) + STAGING_HEALTH_TIMEOUT_SEC ))
    local got_http=0
    while [ "$(date +%s)" -lt "${deadline}" ]; do
        if curl -fsS -o /dev/null "${STAGING_HEALTH_URL}"; then
            got_http=1
            break
        fi
        sleep 5
    done
    if [ "${got_http}" -ne 1 ]; then
        log_error "sanity check: HTTP did not return 200 within ${STAGING_HEALTH_TIMEOUT_SEC}s"
        return 4
    fi
    log_info "sanity check: HTTP ok"

    log_info "sanity check: verifying patient row count matches prod"
    local prod_count staging_count
    prod_count="$(prod_row_count)" || prod_count=-1
    staging_count="$(staging_row_count)" || staging_count=-1
    log_info "sanity check: prod=${prod_count} staging=${staging_count}"
    if [ "${prod_count}" -lt 0 ] || [ "${staging_count}" -lt 0 ]; then
        log_error "sanity check: unable to read patient table"
        return 4
    fi
    if [ "${prod_count}" -ne "${staging_count}" ]; then
        log_error "sanity check: patient count differs (prod=${prod_count}, staging=${staging_count})"
        return 4
    fi
    log_info "sanity check: passed"
    return 0
}

prod_row_count() {
    local prod_compose_dir="${PROD_COMPOSE_DIR:-/volume1/docker/espodental}"
    local prod_env_file="${PROD_ENV_FILE:-${prod_compose_dir}/.env}"
    (
        load_env "${prod_env_file}"
        cd "${prod_compose_dir}" && \
        docker compose exec -T mariadb \
            mariadb -u "${ESPOCRM_DATABASE_USER}" \
                "-p${ESPOCRM_DATABASE_PASSWORD}" \
                -N -B -e "SELECT COUNT(*) FROM \`${PATIENT_TABLE_NAME}\`;" \
                "${ESPOCRM_DATABASE_NAME}" 2>/dev/null \
        | tr -d '[:space:]'
    )
}

staging_row_count() {
    (
        cd "${STAGING_COMPOSE_DIR}" && \
        docker compose -f "${STAGING_COMPOSE_FILE}" exec -T mariadb \
            mariadb -u "${ESPOCRM_DATABASE_USER}" \
                "-p${ESPOCRM_DATABASE_PASSWORD}" \
                -N -B -e "SELECT COUNT(*) FROM \`${PATIENT_TABLE_NAME}\`;" \
                "${ESPOCRM_DATABASE_NAME}" 2>/dev/null \
        | tr -d '[:space:]'
    )
}

main "$@"
