#!/usr/bin/env bash
#
# Validate deployment configuration and recovery docs without touching data.
# This script does not start containers, run backups or restore databases.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if docker compose version >/dev/null 2>&1; then
    COMPOSE=(docker compose)
elif command -v docker-compose >/dev/null 2>&1; then
    COMPOSE=(docker-compose)
else
    printf '[deploy-check] FAIL: docker compose is required.\n' >&2
    exit 127
fi

log() {
    printf '\n[deploy-check] %s\n' "$*"
}

fail() {
    printf '\n[deploy-check] FAIL: %s\n' "$*" >&2
    exit 1
}

assert_contains() {
    local path="$1"
    local needle="$2"

    if ! grep -Fq "${needle}" "${path}"; then
        fail "${path} does not contain ${needle}"
    fi
}

compose_config() {
    local label="$1"
    shift

    log "Validating ${label} compose config."
    "${COMPOSE[@]}" "$@" config --quiet
}

cd "${ROOT_DIR}"

compose_config "local" -f deploy/local/docker-compose.yml
compose_config "production" --env-file deploy/.env.example -f deploy/docker-compose.yml
compose_config "staging" --env-file deploy/staging/.env.example -f deploy/staging/docker-compose.yml

log "Checking shell syntax for backup and restore scripts."
for script in \
    deploy/backup.sh \
    deploy/scripts/backup-prod.sh \
    deploy/scripts/restore-to-staging.sh \
    deploy/scripts/nightly.sh \
    deploy/scripts/lib/common.sh \
    deploy/scripts/lib/alert.sh
do
    bash -n "${script}"
done

log "Checking deployment and recovery runbooks."
assert_contains docs/install-synology.md "espo-dental-bootstrap"
assert_contains docs/install-synology.md "EspoDental nightly backup"
assert_contains docs/admin-guide.md "restore-to-staging.sh"
assert_contains docs/admin-guide.md "backup-prod.sh"
assert_contains docs/proxmox-vm-migration.md "Restore Into The Proxmox VM"
assert_contains docs/proxmox-vm-migration.md "rollback"
assert_contains docs/proxmox-vm-migration.md "espo-dental-bootstrap"

log "Deploy-readiness check PASSED."
