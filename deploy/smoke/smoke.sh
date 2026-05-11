#!/usr/bin/env bash
#
# EspoDental smoke test
# ---------------------
# Boots a disposable MariaDB + EspoCRM 9.2.7 stack with the module sources
# mounted into /var/www/html/custom and /var/www/html/client/custom, then
# waits for HTTP 200 on the installer page and checks that the module's
# entry-point files are visible inside the container.
#
# Requirements on the host: docker (with the compose v2 plugin) and curl.
# Exit code: 0 = healthy, non-zero = failure.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
COMPOSE_FILE="${SCRIPT_DIR}/docker-compose.smoke.yml"
PORT="${SMOKE_PORT:-18080}"
TIMEOUT="${SMOKE_TIMEOUT:-180}"

log() { printf '\n[smoke] %s\n' "$*"; }
cleanup() {
    log "Tearing down stack..."
    docker compose -f "${COMPOSE_FILE}" down -v --remove-orphans >/dev/null 2>&1 || true
}
trap cleanup EXIT

log "Booting smoke stack..."
docker compose -f "${COMPOSE_FILE}" up -d --quiet-pull

log "Waiting for EspoCRM to respond on http://localhost:${PORT}/ (max ${TIMEOUT}s)..."
deadline=$(( $(date +%s) + TIMEOUT ))
status=0
while [[ "$(date +%s)" -lt "${deadline}" ]]; do
    if curl -fsS -o /dev/null "http://localhost:${PORT}/"; then
        status=200
        break
    fi
    sleep 4
done

if [[ "${status}" != "200" ]]; then
    log "FAIL: web container did not respond within ${TIMEOUT}s."
    docker compose -f "${COMPOSE_FILE}" logs --tail=50 smoke-web || true
    exit 1
fi
log "EspoCRM is up."

log "Checking that module sources are mounted..."
required=(
    "/var/www/html/custom/Espo/Modules/EspoDental/Resources/metadata/scopes/Patient.json"
    "/var/www/html/custom/Espo/Modules/EspoDental/Resources/metadata/scopes/OrthodonticCard.json"
    "/var/www/html/custom/Espo/Modules/EspoDental/Resources/routes.json"
    "/var/www/html/client/custom/modules/espo-dental/src/lib/resource-grid.js"
)
for f in "${required[@]}"; do
    if ! docker compose -f "${COMPOSE_FILE}" exec -T smoke-web test -f "${f}"; then
        log "FAIL: missing ${f}"
        exit 2
    fi
done

log "Checking that JSON entry points parse..."
if ! docker compose -f "${COMPOSE_FILE}" exec -T smoke-web \
    php -r 'foreach (["custom/Espo/Modules/EspoDental/Resources/routes.json"] as $f) {
        $j = json_decode(file_get_contents("/var/www/html/" . $f), true);
        if (!is_array($j)) { fwrite(STDERR, "Bad JSON: $f\n"); exit(1); }
        fwrite(STDOUT, "OK $f " . count($j) . " routes\n");
    }'; then
    log "FAIL: JSON parse failed."
    exit 3
fi

log "Smoke test PASSED."
