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

## POC Yack (voir docs/POC_YACK.md)
YACK_CONSOLE = $(DOCKER_COMPOSE) exec -T -e XDEBUG_MODE=off php bin/console

# Données ICU complètes (formats fr_FR des prix) : l'image PHP alpine n'embarque que l'anglais. À relancer après recréation du conteneur.
yack-icu:
	@$(DOCKER_COMPOSE) exec -T -u root php apk add --no-cache icu-data-full >/dev/null
	@$(DOCKER_COMPOSE) exec -T -u root php sh -c 'kill -USR2 1'

# Base vierge + migrations + fixtures Yack (pages CMS comprises) + clés JWT de l'API + assets (le stack doit tourner : make up)
yack-reset: yack-icu
	@$(YACK_CONSOLE) doctrine:database:drop --force --if-exists
	@$(YACK_CONSOLE) doctrine:database:create
	@$(YACK_CONSOLE) doctrine:migrations:migrate -n
	@$(YACK_CONSOLE) sylius:fixtures:load yack -n
	@$(YACK_CONSOLE) lexik:jwt:generate-keypair --skip-if-exists
	@$(YACK_CONSOLE) assets:install public
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) run --rm nodejs

# Synchronisation PIM/ERP simulée. Variable: FEED (défaut: data/yack/api_feed_update.json)
yack-sync:
	@$(YACK_CONSOLE) app:yack:catalog:sync $(or $(FEED),data/yack/api_feed_update.json)

# Builder / pages CMS (SvelteKit, dossier cms/) : http://localhost:5173 — voir docs/POC_YACK.md
yack-cms:
	@DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) up -d cms
