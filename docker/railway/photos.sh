#!/bin/sh
# Railway, programme photos de supervisord (docs/HEBERGEMENT_RAILWAY.md) : les photos du catalogue absentes du volume
# sont téléchargées (data/yack/images/reel/telecharger.sh, réglages YACK_PHOTOS_* du service), puis rattachées aux
# produits chargés avec le visuel de repli (app:yack:catalog:photos : l'image seule change, rien d'autre). Un passage
# sans photo manquante ne fait aucun appel réseau ; relancer le conteneur relance le passage.
set -u
cd /srv/sylius || exit 1

PHOTOS_DIR="${YACK_DATA_DIR:-/data}/photos"
echo "photos : passage en arrière-plan sur $PHOTOS_DIR"
bash /usr/local/share/yack/photos/telecharger.sh "$PHOTOS_DIR"
php -d memory_limit=-1 bin/console --no-interaction app:yack:catalog:photos \
    || echo "photos : rattachement aux produits impossible (message ci-dessus) ; il sera retenté au prochain démarrage"
echo "photos : passage terminé"
