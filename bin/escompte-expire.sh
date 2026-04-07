#!/usr/bin/env bash
# Lance la commande de suppression des escomptes expirés.
# Usage: ./bin/escompte-expire.sh [--dry-run]

set -e

DRY_RUN=""
if [[ "${1:-}" == "--dry-run" ]]; then
    DRY_RUN="--dry-run"
fi

docker compose exec php bin/console app:escompte:expire $DRY_RUN
