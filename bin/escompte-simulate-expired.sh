#!/usr/bin/env bash
# Recule created_at d'une commande pour simuler un délai d'escompte dépassé.
# Usage: ./bin/escompte-simulate-expired.sh <ORDER_NUMBER> [DAYS_AGO]
# Exemple: ./bin/escompte-simulate-expired.sh 000000025 30

set -e

ORDER_NUMBER="${1:-}"
DAYS_AGO="${2:-30}"

if [[ -z "$ORDER_NUMBER" ]]; then
    echo "Usage: $0 <ORDER_NUMBER> [DAYS_AGO]"
    echo "Exemple: $0 000000025 30"
    exit 1
fi

TARGET_DATE=$(date -d "-${DAYS_AGO} days" '+%Y-%m-%d %H:%M:%S')

echo "→ Commande $ORDER_NUMBER : created_at mis à '$TARGET_DATE' (il y a ${DAYS_AGO} jours)"

docker compose exec php sh -c "php bin/console dbal:run-sql \"UPDATE sylius_order SET created_at = '${TARGET_DATE}' WHERE number = '${ORDER_NUMBER}'\"" 2>&1 | grep -v "^\["

echo "✓ Fait. Lance './bin/escompte-expire.sh' pour appliquer l'expiry."
