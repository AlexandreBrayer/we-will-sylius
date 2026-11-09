# Hébergement de démo sur Railway

Le POC tourne sur Railway dans un projet à trois services, à une seule adresse publique pour la boutique, le builder et le back-office.

| Service | Contenu | Adresse |
|---|---|---|
| `yack-app` | image `docker/railway/Dockerfile` : nginx, PHP-FPM (Sylius 2.2 : back-office, API, e-mails) et Node (boutique et builder SvelteKit, GrapesJS), lancés par supervisord ; volume monté sur `/data` | domaine public Railway (`*.up.railway.app`) |
| `MySQL` | base Railway (modèle MySQL) | privée |
| `mailpit` | image `axllent/mailpit` : reçoit les e-mails de la démo (activation, commandes, contact) et les affiche | domaine public, protégé par identifiant |

Le découpage des URL est celui de la stack locale (`etc/nginx/default.conf.template`) : `/admin`, `/api`, `/media`, `/bundles`, `/build` vont à Sylius ou aux fichiers de `public/`, le reste au front. Le front appelle l'API par le nginx du conteneur (`http://127.0.0.1:8080`).

## Démarrage du conteneur (`docker/railway/entrypoint.sh`)

1. `public/media` (photos, vignettes, médias du builder) et `var/yack/private` (Kbis, photos de plaque, devis) pointent sur le volume `/data` ; les clés JWT y sont aussi (`/data/jwt`).
2. Base vide : migrations puis fixtures Yack (catalogue, comptes de démo, SAV, pages et templates CMS). Base remplie : migrations seules.
3. `YACK_RESET_ON_BOOT=1` vide la base au démarrage ; les migrations et les fixtures Yack la reconstruisent ; ce qui a été saisi à la main et les fichiers privés sont perdus. On remet la variable à `0` après le redémarrage.
4. Cache Symfony, `assets:install`, puis nginx, PHP-FPM et le front.

Les photos du catalogue sont téléchargées à la construction de l'image (`data/yack/images/reel/telecharger.sh`, CDN de yack.fr) ; une photo absente prend le visuel de repli.

## Variables du service `yack-app`

| Variable | Valeur |
|---|---|
| `PORT` | `8080` |
| `APP_SECRET` | chaîne aléatoire |
| `JWT_PASSPHRASE` | chaîne aléatoire, fixée avant le premier démarrage (les clés de `/data/jwt` en dépendent) |
| `DATABASE_URL` | `mysql://${{MySQL.MYSQLUSER}}:${{MySQL.MYSQLPASSWORD}}@${{MySQL.MYSQLHOST}}:${{MySQL.MYSQLPORT}}/${{MySQL.MYSQLDATABASE}}?charset=utf8mb4` |
| `FRONT_URL`, `DEFAULT_URI` | `https://${{RAILWAY_PUBLIC_DOMAIN}}` (liens des e-mails) |
| `MAILER_DSN` | `smtp://${{mailpit.RAILWAY_PRIVATE_DOMAIN}}:1025` |
| `YACK_DEMO_BAR` | `1` (barre de profils et identifiants de démo) |
| `YACK_ASSISTANCE` | `1` |
| `YACK_SEARCH_ENGINE` | `database` |
| `YACK_AI_ADAPTER` | `fake` tant que les vraies clés ne sont pas posées (variables `YACK_AI_*`, `MISTRAL_*`, `ANTHROPIC_API_KEY` de `.env`, à saisir dans Railway, jamais dans le dépôt) |
| `YACK_RESET_ON_BOOT` | `0` |

Service `mailpit` : `MP_SMTP_BIND_ADDR=[::]:1025`, `MP_UI_BIND_ADDR=[::]:8025`, `PORT=8025`, `MP_UI_AUTH=demo:<mot de passe>`.

## Déployer

Depuis la racine du dépôt, CLI Railway liée au projet (`railway link`) : `railway up --service yack-app`. La CLI envoie le dossier en suivant `.gitignore` (ni `vendor/`, ni `node_modules/`, ni `var/`, ni `.env.local`) ; Railway construit l'image et la démarre. Le dépôt GitLab n'intervient pas.

Avant d'envoyer, on peut construire l'image en local : `docker build -f docker/railway/Dockerfile -t yack-railway .`

## Manuels et notices (PDF)

Les PDF des fabricants ne sont ni dans le dépôt ni dans l'image : ils vivent sur le volume (`/data/manuels`), remplis depuis leurs liens officiels, avec l'accord de Yack avant toute mise en production (ADR 0008).

## Limites

- Démo seulement : une instance, dépendances PHP de développement installées, `APP_ENV=prod`, pas de worker Messenger (comme la stack locale).
- Meilisearch n'est pas déployé : la recherche passe par la base.
