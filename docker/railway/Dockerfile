# syntax=docker/dockerfile:1
# Image unique de démo pour Railway (docs/HEBERGEMENT_RAILWAY.md) : nginx + PHP-FPM (Sylius : back-office, API, e-mails)
# + Node (boutique et builder SvelteKit), lancés par supervisord dans un seul conteneur, une seule adresse publique.
# Même découpage que la stack Docker locale (etc/nginx/default.conf.template), sans Docker Compose.
# Tout ce qui ne dépend pas de la base est préparé ici (dépendances, assets, cache Symfony) : le démarrage ne fait que
# le volume, la base et les clés.

ARG PHP_IMAGE=ghcr.io/sylius/sylius-php:8.3-alpine
ARG NODE_IMAGE=node:22-alpine

# --- Dépendances PHP de prod (sans les outils de test et d'analyse) ; couche en cache tant que composer.lock ne change pas
FROM ${PHP_IMAGE} AS vendor
WORKDIR /srv/sylius
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-progress --no-autoloader --prefer-dist

# --- Assets Sylius et feuille de style Yack, build de prod (minifié, noms versionnés). Encore lit les paquets de vendor/ ;
# seuls les fichiers du build sont copiés, un changement de code PHP ne relance pas yarn.
FROM ${NODE_IMAGE} AS assets
WORKDIR /srv/sylius
COPY package.json yarn.lock ./
COPY --from=vendor /srv/sylius/vendor ./vendor
RUN yarn install --frozen-lockfile --non-interactive
COPY .babelrc webpack.config.js ./
COPY assets ./assets
RUN yarn build:prod
# Le front lie deux feuilles de style par URL fixe (front/src/routes/+layout.svelte, front/src/lib/editor/grapesSetup.ts) :
# une copie sans empreinte à côté du fichier versionné (nginx la fait revalider, les fichiers versionnés sont gardés un an).
RUN node -e ' \
    const fs = require("fs"); \
    for (const dir of ["public/build/shop", "public/build/app/shop"]) { \
        const manifest = JSON.parse(fs.readFileSync(dir + "/manifest.json", "utf8")); \
        for (const [name, file] of Object.entries(manifest)) \
            if (name.endsWith(".css")) fs.copyFileSync("public" + file, "public/" + name); \
    }'

# --- Front SvelteKit (adapter-node), comme FRONT_MODE=prod ; seules les dépendances d'exécution restent dans node_modules
FROM ${NODE_IMAGE} AS front
WORKDIR /srv/sylius/front
COPY front/package.json front/package-lock.json front/.npmrc ./
RUN npm ci --no-audit --no-fund
COPY front/ ./
RUN npm run build && npm prune --omit=dev --no-audit --no-fund

# --- Photos du catalogue (CDN de yack.fr, non versionnées) : elles remplissent le volume au démarrage, sans réseau.
# Un échec réseau ne bloque pas la construction : le résumé reste dans le journal (« photos présentes : … ») et le
# démarrage télécharge sur le volume les photos qui manquent (docker/railway/photos.sh). Étape en cache tant que le
# script ne change pas : le journal affiche alors « CACHED » sans résumé.
FROM ${NODE_IMAGE} AS photos
RUN apk add --no-cache bash curl
COPY data/yack/images/reel/telecharger.sh /photos/telecharger.sh
RUN YACK_PHOTOS_BUDGET=240 bash /photos/telecharger.sh /photos \
    || echo "ATTENTION : photos manquantes dans l'image ; le démarrage les télécharge sur le volume"

FROM ${NODE_IMAGE} AS node-runtime

# --- Image finale
FROM ${PHP_IMAGE}
# icu-data-full : formats fr_FR des prix (même raison que make yack-icu) ; libstdc++ et libgcc : binaire node copié ci-dessous ;
# bash et curl : téléchargement des photos au démarrage
RUN apk add --no-cache nginx supervisor icu-data-full libstdc++ libgcc bash curl
COPY --from=node-runtime /usr/local/bin/node /usr/local/bin/node

COPY docker/railway/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/railway/supervisord.conf /etc/supervisord.conf
COPY docker/railway/php-fpm.conf /usr/local/etc/php-fpm.d/zz-railway.conf
COPY docker/railway/php.ini /usr/local/etc/php/conf.d/zz-railway.ini
COPY etc/php/yack-uploads.ini /usr/local/etc/php/conf.d/zz-yack-uploads.ini
COPY docker/railway/entrypoint.sh /usr/local/bin/yack-railway-entrypoint
COPY docker/railway/photos.sh /usr/local/bin/yack-railway-photos
RUN chmod +x /usr/local/bin/yack-railway-entrypoint /usr/local/bin/yack-railway-photos
COPY --from=photos /photos/ /usr/local/share/yack/photos/

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    YACK_DATA_DIR=/data \
    JWT_SECRET_KEY=/data/jwt/private.pem \
    JWT_PUBLIC_KEY=/data/jwt/public.pem \
    SYLIUS_FIXTURES_HOSTNAME=localhost \
    PORT=8080

WORKDIR /srv/sylius
COPY --from=vendor /srv/sylius/vendor ./vendor
COPY . ./
COPY --from=assets /srv/sylius/public/build ./public/build
COPY --from=front /srv/sylius/front/build ./front/build
COPY --from=front /srv/sylius/front/node_modules ./front/node_modules

# Autoloader figé, .env compilé (les variables du service Railway restent prioritaires), cache Symfony de prod chauffé
# (préchargé par OPcache, docker/railway/php.ini) et assets des bundles : rien de tout ça ne dépend de la base.
RUN composer dump-autoload --no-dev --classmap-authoritative --no-interaction \
    && composer dump-env prod \
    && php -d memory_limit=-1 bin/console cache:warmup \
    && php bin/console assets:install public \
    && chown -R www-data:www-data var

EXPOSE 8080
ENTRYPOINT ["/usr/local/bin/yack-railway-entrypoint"]
