#!/bin/sh
# Railway : démarrage du conteneur de démo (docs/HEBERGEMENT_RAILWAY.md).
# 1. fichiers durables sur le volume (photos, vignettes, médias du builder, fichiers privés, clés JWT) ;
# 2. base prête : premier démarrage = migrations + fixtures Yack ; ensuite migrations seules ;
# 3. cache Symfony, assets, puis nginx + PHP-FPM + front sous supervisord.
set -eu
cd /srv/sylius

DATA_DIR="${YACK_DATA_DIR:-/data}"
console() { php -d memory_limit=-1 bin/console --no-interaction "$@"; }

mkdir -p "$DATA_DIR/media/image" "$DATA_DIR/private" "$DATA_DIR/jwt"
rm -rf public/media && ln -s "$DATA_DIR/media" public/media
mkdir -p var/yack && rm -rf var/yack/private && ln -s "$DATA_DIR/private" var/yack/private

echo "Attente de la base…"
i=0
until console dbal:run-sql "SELECT 1" >/dev/null 2>&1; do
    i=$((i + 1))
    if [ "$i" -ge 60 ]; then
        echo "Base injoignable après 120 s : vérifier DATABASE_URL" >&2
        console dbal:run-sql "SELECT 1" || true
        exit 1
    fi
    sleep 2
done

rm -rf var/cache/*
console cache:clear

# DESTRUCTIF, seulement si YACK_RESET_ON_BOOT=1 : la base est vidée. Qui reconstruit ? Les migrations et les fixtures Yack
# juste en dessous. Perdu : ce qui a été saisi à la main (comptes, commandes, pages modifiées) et les fichiers privés.
if [ "${YACK_RESET_ON_BOOT:-0}" = "1" ]; then
    echo "YACK_RESET_ON_BOOT=1 : base vidée, reconstruite par les migrations et les fixtures Yack"
    console doctrine:schema:drop --full-database --force
    rm -rf "${DATA_DIR:?}/private/"*
fi

if console dbal:run-sql "SELECT 1 FROM sylius_channel LIMIT 1" >/dev/null 2>&1; then
    console doctrine:migrations:migrate --allow-no-migration
else
    echo "Base vide : migrations puis fixtures Yack (catalogue, comptes de démo, SAV, pages CMS)"
    console doctrine:migrations:migrate --allow-no-migration
    console sylius:fixtures:load yack
fi

console lexik:jwt:generate-keypair --skip-if-exists
console assets:install public
console cache:warmup

# PHP-FPM et le front tournent sous www-data : cache, journaux et volume lui reviennent.
mkdir -p "$DATA_DIR/media/cache"
chown -R www-data:www-data var "$DATA_DIR"

exec supervisord -c /etc/supervisord.conf
