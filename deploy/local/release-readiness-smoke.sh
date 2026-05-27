#!/usr/bin/env bash
#
# Local release-readiness smoke for the EspoDental demo stack.
#
# The script prepares the local Docker stack, runs rebuild/bootstrap/demo seed,
# then checks read-only API surfaces used by the Stage K acceptance demo. It
# deliberately does not call provider-send, payment, visit-finish, inventory
# write or assistant-apply actions.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/../.." && pwd)"
COMPOSE_FILE="${COMPOSE_FILE:-${SCRIPT_DIR}/docker-compose.yml}"
SERVICE="${ESPO_SERVICE:-espocrm}"
AUTH="${ESPO_AUTH:-admin:espodental-admin}"
PORT="${ESPOCRM_HTTP_PORT:-18080}"
BASE_URL="${ESPO_API_BASE_URL:-http://localhost/api/v1}"
HOST_URL="${ESPO_HOST_URL:-http://localhost:${PORT}}"
TIMEOUT="${ESPO_READY_TIMEOUT:-180}"

if docker compose version >/dev/null 2>&1; then
    COMPOSE=(docker compose)
elif command -v docker-compose >/dev/null 2>&1; then
    COMPOSE=(docker-compose)
else
    printf '[release-smoke] FAIL: docker compose is required.\n' >&2
    exit 127
fi

log() {
    printf '\n[release-smoke] %s\n' "$*"
}

fail() {
    printf '\n[release-smoke] FAIL: %s\n' "$*" >&2
    exit 1
}

assert_contains() {
    local body="$1"
    local needle="$2"
    local label="$3"

    if [[ "${body}" != *"${needle}"* ]]; then
        fail "${label} did not contain ${needle}"
    fi
}

api_get() {
    local path="$1"

    "${COMPOSE[@]}" -f "${COMPOSE_FILE}" exec -T "${SERVICE}" \
        curl -fsS -u "${AUTH}" "${BASE_URL}/${path}"
}

cd "${ROOT_DIR}"

log "Starting local stack."
"${COMPOSE[@]}" -f "${COMPOSE_FILE}" up -d

log "Waiting for EspoCRM on ${HOST_URL}."
deadline=$(( $(date +%s) + TIMEOUT ))
until curl -fsS -o /dev/null "${HOST_URL}/"; do
    if [[ "$(date +%s)" -ge "${deadline}" ]]; then
        fail "EspoCRM did not become ready within ${TIMEOUT}s"
    fi
    sleep 4
done

if [[ "${ESPO_RELEASE_SKIP_SETUP:-0}" != "1" ]]; then
    log "Running rebuild, bootstrap and demo seed."
    "${COMPOSE[@]}" -f "${COMPOSE_FILE}" exec -T "${SERVICE}" php rebuild.php
    "${COMPOSE[@]}" -f "${COMPOSE_FILE}" exec -T "${SERVICE}" php command.php espo-dental-bootstrap
    "${COMPOSE[@]}" -f "${COMPOSE_FILE}" exec -T "${SERVICE}" php command.php espo-dental-demo-seed
else
    log "Skipping rebuild/bootstrap/demo seed because ESPO_RELEASE_SKIP_SETUP=1."
fi

log "Checking integration health and Stage J acceptance."
health="$(api_get 'EspoDental/Integration/healthcheck?limit=2')"
assert_contains "${health}" '"stageJAcceptance"' 'integration healthcheck'
assert_contains "${health}" '"directMutationToolCount":0' 'integration healthcheck'
assert_contains "${health}" 'restrictedMcpRoutes=0' 'integration healthcheck'
assert_contains "${health}" '"queue_preflight"' 'integration healthcheck'
assert_contains "${health}" '"credential_gate"' 'integration healthcheck'

log "Checking management snapshot."
snapshot="$(api_get 'EspoDental/Report/managementSnapshot?limit=2')"
assert_contains "${snapshot}" '"finance"' 'management snapshot'
assert_contains "${snapshot}" '"doctorRows"' 'management snapshot'
assert_contains "${snapshot}" '"cabinetRows"' 'management snapshot'
assert_contains "${snapshot}" '"payroll"' 'management snapshot'

log "Checking report export."
export_json="$(api_get 'EspoDental/Report/export?source=finance&format=json&limit=2')"
assert_contains "${export_json}" '"source":"finance"' 'report export'
assert_contains "${export_json}" '"format":"json"' 'report export'
assert_contains "${export_json}" '"content"' 'report export'

log "Checking inventory workspace."
inventory="$(api_get 'EspoDental/Inventory/workspace?limit=2')"
assert_contains "${inventory}" '"warehouses"' 'inventory workspace'
assert_contains "${inventory}" '"stockLots"' 'inventory workspace'
assert_contains "${inventory}" '"recentMovements"' 'inventory workspace'

log "Release-readiness smoke PASSED."
