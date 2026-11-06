.PHONY: run

DOCKER_COMPOSE ?= docker compose
# Avoid using root (0:0) as DOCKER_USER, especially in WSL2 environments
# If id -u returns 0, fall back to 1000:1000 to prevent fixuid/PHP-FPM issues
DOCKER_USER ?= $(shell if [ "$$(id -u)" = "0" ]; then echo "1000:1000"; else echo "$$(id -u):$$(id -g)"; fi)
ENV ?= "dev"

init:
	@make -s docker-compose-check
	@if [ "$$(id -u)" = "0" ]; then \
		echo "Running as root (WSL2/Linux) - preparing permissions for Docker containers..."; \
		chmod -R 777 var/ public/ 2>/dev/null || true; \
		chown -R 1000:1000 vendor/ node_modules/ 2>/dev/null || true; \
	fi
	@if [ ! -e compose.override.yml ]; then \
		cp compose.override.dist.yml compose.override.yml; \
	fi
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) run --rm php composer install --no-interaction --no-scripts
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) run --rm nodejs
	@make -s install
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) up -d

run:
	@make -s up

debug:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) -f compose.yml -f compose.override.yml -f compose.debug.yml up -d

up:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) up -d

down:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) down

install:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) run --rm php bin/console sylius:install -s default -n

clean:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) down -v

php-shell:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) exec php sh

node-shell:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) run --rm -i nodejs sh

node-watch:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) run --rm -i nodejs "npm run watch"

docker-compose-check:
	@$(DOCKER_COMPOSE) version >/dev/null 2>&1 || (echo "Please install docker compose binary or set DOCKER_COMPOSE=\"docker-compose\" for legacy binary" && exit 1)
	@echo "You are using \"$(DOCKER_COMPOSE)\" binary"
	@echo "Current version is \"$$($(DOCKER_COMPOSE) version)\""

## Escompte
# Simule un délai dépassé sur une commande. Variables: ORDER (obligatoire), DAYS (défaut: 30)
# Exemple: make escompte-simulate ORDER=000000025
# Exemple: make escompte-simulate ORDER=000000025 DAYS=25
escompte-simulate:
	@test -n "$(ORDER)" || (echo "Usage: make escompte-simulate ORDER=<numéro> [DAYS=<jours>]" && exit 1)
	@bash bin/escompte-simulate-expired.sh $(ORDER) $(or $(DAYS),30)

# Lance la suppression des escomptes expirés (mode dry-run)
escompte-expire-dry:
	@bash bin/escompte-expire.sh --dry-run

# Lance la suppression des escomptes expirés (réel)
escompte-expire:
	@bash bin/escompte-expire.sh

## POC Yack (voir docs/POC_YACK.md) — boutique headless SvelteKit (front/) + Sylius (admin, API, e-mails)
# make yack-reset && make yack-up → http://localhost:8088 (FRONT_MODE=prod : build adapter-node au démarrage du front)
.PHONY: yack-reset yack-up yack-icu yack-sync
YACK_HTTP_PORT ?= 8088
YACK_COMPOSE = HTTP_PORT=$(YACK_HTTP_PORT) ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE)
YACK_CONSOLE = $(YACK_COMPOSE) exec -T -e XDEBUG_MODE=off php bin/console
# Services du socle (php, mysql, mailhog… et meilisearch s'il est déclaré) : tout sauf le front, nginx et le build d'assets
YACK_BACKEND = $$($(YACK_COMPOSE) config --services | grep -vxE 'front|nginx|nodejs')

# Données ICU complètes (formats fr_FR des prix) : l'image PHP alpine n'embarque que l'anglais. Perdues à chaque recréation du conteneur (relancé par yack-up).
yack-icu:
	@$(YACK_COMPOSE) exec -T -u root php apk add --no-cache icu-data-full >/dev/null
	@$(YACK_COMPOSE) exec -T -u root php sh -c 'kill -USR2 1'

# Reconstruction complète : dépendances PHP, base vierge + migrations + fixtures Yack (catalogue, SAV, pages CMS),
# clés JWT de l'API, assets Sylius/Encore (admin + feuille de style Yack du front), dépendances du front (npm ci).
yack-reset:
	@test -e compose.override.yml || cp compose.override.dist.yml compose.override.yml
	@$(YACK_COMPOSE) up -d --wait $(YACK_BACKEND)
	@$(YACK_COMPOSE) exec -T php composer install -n --no-scripts --no-progress
	@$(MAKE) -s yack-icu
	@$(YACK_CONSOLE) cache:clear
	@$(YACK_CONSOLE) doctrine:database:drop --force --if-exists
	@$(YACK_CONSOLE) doctrine:database:create
	@$(YACK_CONSOLE) doctrine:migrations:migrate -n
	@$(YACK_CONSOLE) sylius:fixtures:load yack -n
	@$(YACK_CONSOLE) lexik:jwt:generate-keypair --skip-if-exists
	@$(YACK_CONSOLE) assets:install public
	@$(YACK_COMPOSE) run --rm nodejs
	@$(YACK_COMPOSE) rm -sf front >/dev/null 2>&1 || true
	@$(YACK_COMPOSE) run --rm --no-deps front sh -c 'npm ci --no-audit --no-fund'
	@curl -fs -X DELETE http://localhost:8025/api/v1/messages >/dev/null || true
	@echo "Base, assets, dépendances prêts et boîte MailHog vidée : make yack-up"

# Démarre tout (socle, nginx, front SvelteKit, MailHog) et attend que la boutique réponde sur http://localhost:$(YACK_HTTP_PORT)
yack-up:
	@$(YACK_COMPOSE) up -d --wait $(YACK_BACKEND)
	@$(MAKE) -s yack-icu
	@$(YACK_COMPOSE) up -d front nginx
	@echo "Démarrage du front ($${FRONT_MODE:-dev})…"
	@for i in $$(seq 1 300); do curl -fs -o /dev/null http://localhost:$(YACK_HTTP_PORT)/ && break; sleep 1; done; \
		curl -fsS -o /dev/null http://localhost:$(YACK_HTTP_PORT)/ || { echo "Le front ne répond pas : $(DOCKER_COMPOSE) logs front"; exit 1; }
	@echo "Boutique  http://localhost:$(YACK_HTTP_PORT)/"
	@echo "Admin     http://localhost:$(YACK_HTTP_PORT)/admin/ (admin@yack.test / yack)"
	@echo "Builder   http://localhost:$(YACK_HTTP_PORT)/cms-admin/builder"
	@echo "MailHog   http://localhost:8025/"

# Synchronisation PIM/ERP simulée. Variable: FEED (défaut: data/yack/api_feed_update.json)
yack-sync:
	@$(YACK_CONSOLE) app:yack:catalog:sync $(or $(FEED),data/yack/api_feed_update.json)
