#!/bin/sh
# Railway : démarrage du conteneur de démo (docs/HEBERGEMENT_RAILWAY.md).
# 1. fichiers durables sur le volume (photos du catalogue, images des produits, vignettes, médias du builder, fichiers
#    privés, clés JWT) ;
# 2. base prête : premier démarrage = migrations + fixtures Yack ; ensuite migrations seules ;
# 3. cache Symfony, assets, puis nginx + PHP-FPM + front sous supervisord, et en arrière-plan les photos du catalogue
#    qui manquent encore (docker/railway/photos.sh).
set -eu
cd /srv/sylius

DATA_DIR="${YACK_DATA_DIR:-/data}"
console() { php -d memory_limit=-1 bin/console --no-interaction "$@"; }

mkdir -p "$DATA_DIR/media/image" "$DATA_DIR/private" "$DATA_DIR/jwt"
rm -rf public/media && ln -s "$DATA_DIR/media" public/media
mkdir -p var/yack && rm -rf var/yack/private && ln -s "$DATA_DIR/private" var/yack/private

# Photos du catalogue : sur le volume, elles survivent aux redéploiements et aux remises à zéro ; l'application les lit
# à l'adresse du dépôt (data/yack/images/reel), qui devient un lien vers le volume. Celles de l'image (construction)
# remplissent le volume sans réseau ; une copie impossible (volume plein) ne bloque pas le démarrage. Le dossier remplacé
# par le lien ne contient, dans l'image, que le script de téléchargement, gardé dans /usr/local/share/yack/photos.
PHOTOS_DIR="$DATA_DIR/photos"
PHOTOS_IMAGE=/usr/local/share/yack/photos
mkdir -p "$PHOTOS_DIR"
copiees=0
for photo in "$PHOTOS_IMAGE"/*.jpg "$PHOTOS_IMAGE"/*.png "$PHOTOS_IMAGE"/*.webp; do
    if [ -s "$photo" ] && [ ! -s "$PHOTOS_DIR/${photo##*/}" ]; then
        if cp "$photo" "$PHOTOS_DIR/"; then
            copiees=$((copiees + 1))
        else
            echo "Photos du catalogue : copie de ${photo##*/} impossible sur le volume" >&2
        fi
    fi
done
echo "Photos du catalogue : $(find "$PHOTOS_DIR" -maxdepth 1 -type f ! -name '.*' | wc -l) sur le volume, dont $copiees copiées depuis l'image"
rm -rf data/yack/images/reel && ln -s "$PHOTOS_DIR" data/yack/images/reel

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
