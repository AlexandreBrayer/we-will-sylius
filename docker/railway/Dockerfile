# syntax=docker/dockerfile:1
# Image unique de démo pour Railway (docs/HEBERGEMENT_RAILWAY.md) : nginx + PHP-FPM (Sylius : back-office, API, e-mails)
# + Node (boutique et builder SvelteKit), lancés par supervisord dans un seul conteneur, une seule adresse publique.
# Même découpage que la stack Docker locale (etc/nginx/default.conf.template), sans Docker Compose.

ARG PHP_IMAGE=ghcr.io/sylius/sylius-php:8.3-alpine
ARG NODE_IMAGE=node:22-alpine

# --- Dépendances PHP (mêmes options que make yack-reset : dépendances de dev comprises, scripts lancés au démarrage)
FROM ${PHP_IMAGE} AS vendor
WORKDIR /srv/sylius
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-interaction --no-scripts --no-progress --no-autoloader
COPY . .
RUN composer dump-autoload --optimize --no-interaction

# --- Assets Sylius et feuille de style Yack (Encore lit les paquets de vendor/, comme le service nodejs de compose)
FROM ${NODE_IMAGE} AS assets
WORKDIR /srv/sylius
COPY --from=vendor /srv/sylius /srv/sylius
RUN yarn install --non-interactive && yarn build

# --- Front SvelteKit (adapter-node), comme FRONT_MODE=prod
FROM ${NODE_IMAGE} AS front
WORKDIR /srv/sylius/front
COPY front/package.json front/package-lock.json front/.npmrc ./
RUN npm ci --no-audit --no-fund
COPY front/ ./
RUN npm run build

# --- Photos du catalogue (CDN de yack.fr, non versionnées) : sans photo, l'import prend le visuel de repli
FROM ${NODE_IMAGE} AS photos
RUN apk add --no-cache bash curl
COPY data/yack/images/reel/telecharger.sh /photos/telecharger.sh
RUN bash /photos/telecharger.sh | tail -3 || true

FROM ${NODE_IMAGE} AS node-runtime

# --- Image finale
FROM ${PHP_IMAGE}
# icu-data-full : formats fr_FR des prix (même raison que make yack-icu) ; libstdc++ et libgcc : binaire node copié ci-dessous
RUN apk add --no-cache nginx supervisor icu-data-full libstdc++ libgcc
COPY --from=node-runtime /usr/local/bin/node /usr/local/bin/node
RUN node --version

WORKDIR /srv/sylius
COPY --from=vendor /srv/sylius ./
COPY --from=assets /srv/sylius/public/build ./public/build
COPY --from=front /srv/sylius/front/build ./front/build
COPY --from=front /srv/sylius/front/node_modules ./front/node_modules
COPY --from=photos /photos/ ./data/yack/images/reel/

COPY docker/railway/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/railway/supervisord.conf /etc/supervisord.conf
COPY docker/railway/php-fpm.conf /usr/local/etc/php-fpm.d/zz-railway.conf
COPY docker/railway/php.ini /usr/local/etc/php/conf.d/zz-railway.ini
COPY etc/php/yack-uploads.ini /usr/local/etc/php/conf.d/zz-yack-uploads.ini
COPY docker/railway/entrypoint.sh /usr/local/bin/yack-railway-entrypoint
RUN chmod +x /usr/local/bin/yack-railway-entrypoint

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    YACK_DATA_DIR=/data \
    JWT_SECRET_KEY=/data/jwt/private.pem \
    JWT_PUBLIC_KEY=/data/jwt/public.pem \
    SYLIUS_FIXTURES_HOSTNAME=localhost \
    PORT=8080

EXPOSE 8080
ENTRYPOINT ["/usr/local/bin/yack-railway-entrypoint"]
