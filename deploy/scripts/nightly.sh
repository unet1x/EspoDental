#!/usr/bin/env bash
#
# EspoDental — nightly pipeline (cron-driven).
#
#   1. Back up prod (mariadb-dump + gzip + retention).
#   2. Restore latest backup into staging.
#   3. Sanity-check staging.
#
# If any step fails:
#   * The error is logged with a fixed pipeline_id.
#   * Telegram + email alerts go out via deploy/scripts/lib/alert.sh.
#   * Step 1 is retried ONCE (backups can transiently fail under load).
#   * On second failure the pipeline aborts loudly.
#
# Exit codes:
#   0  ok
#   1  backup failed twice
#   2  restore failed
#   3  sanity check failed

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "${SCRIPT_DIR}/lib/common.sh"
. "${SCRIPT_DIR}/lib/alert.sh"

NIGHTLY_ENV_FILE="${NIGHTLY_ENV_FILE:-/volume1/docker/espodental/.env}"
LOG_DIR="${LOG_DIR:-/volume2/espodental/logs}"
mkdir -p "${LOG_DIR}"
LOG_FILE="${LOG_DIR}/nightly-${ESPODENTAL_PIPELINE_ID}.log"

# Load alert credentials from prod .env (ALERT_* and TELEGRAM_*).
if [ -f "${NIGHTLY_ENV_FILE}" ]; then
    load_env "${NIGHTLY_ENV_FILE}"
fi

run_step() {
    local name="$1"
    shift
    log_info "[step:${name}] starting"
    if "$@"; then
        log_info "[step:${name}] ok"
        return 0
    fi
    local rc=$?
    log_error "[step:${name}] failed (exit ${rc})"
    return "${rc}"
}

backup() {
    bash "${SCRIPT_DIR}/backup-prod.sh"
}

restore() {
    bash "${SCRIPT_DIR}/restore-to-staging.sh"
}

main() {
    exec > >(tee -a "${LOG_FILE}") 2>&1
    log_info "nightly: pipeline_id=${ESPODENTAL_PIPELINE_ID}"
    log_info "nightly: log=${LOG_FILE}"

    if run_step backup-prod backup; then
        :
    else
        log_warn "nightly: first backup attempt failed, retrying in 60s"
        sleep 60
        if ! run_step backup-prod-retry backup; then
            alert_send "Backup FAILED twice" \
"Prod backup failed twice in a row, see ${LOG_FILE}.

Pipeline: ${ESPODENTAL_PIPELINE_ID}
Time:     $(date '+%Y-%m-%d %H:%M:%S %Z')

This is critical — there is no fresh dump for tonight. Investigate the
prod MariaDB container, disk space and credentials."
            return 1
        fi
    fi

    if ! run_step restore-to-staging restore; then
        local rc=$?
        case ${rc} in
            4)
                alert_send "Staging sanity check FAILED" \
"Restore completed but staging did not pass the post-restore sanity check.
Either the backup is corrupted or the staging container is unhealthy.

Recommended action:
  1. Re-run deploy/scripts/backup-prod.sh on prod NAS.
  2. Re-run deploy/scripts/restore-to-staging.sh.
  3. If still failing, treat last night's backup as INVALID and retry
     after dumping prod manually.

Pipeline: ${ESPODENTAL_PIPELINE_ID}
Log:      ${LOG_FILE}"
                return 3
                ;;
            *)
                alert_send "Restore to staging FAILED" \
"Restore script aborted with exit code ${rc}.

The most likely causes are:
  * dump file at /volume2/espodental/backups/db-latest.sql.gz is missing
    or corrupted;
  * staging MariaDB container is not running;
  * disk is full.

Pipeline: ${ESPODENTAL_PIPELINE_ID}
Log:      ${LOG_FILE}"
                return 2
                ;;
        esac
    fi

    log_info "nightly: SUCCESS"
    return 0
}

main "$@"
