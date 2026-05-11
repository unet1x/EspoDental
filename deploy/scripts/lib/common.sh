# Shared helpers for EspoDental ops scripts.
# Sourced (`. common.sh`) by backup-prod.sh, restore-to-staging.sh, nightly.sh.
# Not executable by itself.

set -eu

ESPODENTAL_LOG_LEVEL="${ESPODENTAL_LOG_LEVEL:-info}"
ESPODENTAL_PIPELINE_ID="${ESPODENTAL_PIPELINE_ID:-$(date +%Y%m%dT%H%M%S)-$$}"

log() {
    local level="$1"
    shift
    printf '%s [%s] [%s] %s\n' \
        "$(date '+%Y-%m-%d %H:%M:%S')" \
        "${level}" \
        "${ESPODENTAL_PIPELINE_ID}" \
        "$*"
}

log_info()  { log INFO  "$@"; }
log_warn()  { log WARN  "$@" >&2; }
log_error() { log ERROR "$@" >&2; }

require_env() {
    local missing=""
    for var in "$@"; do
        if [ -z "${!var:-}" ]; then
            missing="${missing} ${var}"
        fi
    done
    if [ -n "${missing}" ]; then
        log_error "Missing required env var(s):${missing}"
        return 1
    fi
}

# Load .env file from path arg, exporting all vars. Ignores comments.
load_env() {
    local env_file="$1"
    if [ ! -f "${env_file}" ]; then
        log_error "Env file not found: ${env_file}"
        return 1
    fi
    # shellcheck disable=SC2046
    set -a
    # shellcheck disable=SC1090
    . "${env_file}"
    set +a
}
