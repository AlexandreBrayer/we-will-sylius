# Hébergement de démo sur Railway

Le POC tourne sur Railway dans un projet à trois services, à une seule adresse publique pour la boutique, le builder et le back-office.

| Service | Contenu | Adresse |
|---|---|---|
| `yack-app` | image `docker/railway/Dockerfile` : nginx, PHP-FPM (Sylius 2.2 : back-office, API, e-mails) et Node (boutique et builder SvelteKit, GrapesJS), lancés par supervisord ; volume monté sur `/data` | domaine public Railway (`*.up.railway.app`) |
| `MySQL` | base Railway (modèle MySQL) | privée |
| `mailpit` | image Docker Hub axllent/mailpit : reçoit les e-mails de la démo (activation, commandes, contact) et les affiche | domaine public, protégé par identifiant |

Le découpage des URL est celui de la stack locale (`etc/nginx/default.conf.template`) : `/admin`, `/api`, `/media`, `/bundles`, `/build` vont à Sylius ou aux fichiers de `public/`, le reste au front. Le front appelle l'API par le nginx du conteneur (`http://127.0.0.1:8080`).

## Démarrage du conteneur (`docker/railway/entrypoint.sh`)

1. `public/media` (images des produits, vignettes, médias du builder) et `var/yack/private` (Kbis, photos de plaque, devis) pointent sur le volume `/data` ; les clés JWT y sont aussi (`/data/jwt`).
2. Les photos du catalogue vivent sur le volume (`/data/photos`) et `data/yack/images/reel` devient un lien vers ce dossier (détail plus bas).
3. Base vide : migrations puis fixtures Yack (catalogue, comptes de démo, SAV, pages et templates CMS). Base remplie : migrations seules.
4. `YACK_RESET_ON_BOOT=1` vide la base au démarrage ; les migrations et les fixtures Yack la reconstruisent ; ce qui a été saisi à la main et les fichiers privés sont perdus, les photos du catalogue restent. On remet la variable à `0` après le redémarrage.
5. Cache Symfony, `assets:install`, puis supervisord lance nginx, PHP-FPM, le front et, en arrière-plan, le passage des photos (`docker/railway/photos.sh`).

## Photos du catalogue

Les 291 photos des produits finis viennent du CDN de yack.fr (`data/yack/images/reel/telecharger.sh`) ; elles ne sont pas dans le dépôt. Une photo absente au chargement du catalogue est remplacée par le visuel de repli du produit.

- **Construction de l'image** : l'étape `photos` du Dockerfile les télécharge et les range dans l'image. Un échec réseau ne bloque pas la construction ; le journal de construction montre le résumé (`photos présentes : 0, téléchargées : 291, échecs : 0, non tentées : 0`), ou « CACHED » quand le script n'a pas changé.
- **Démarrage** : les photos de l'image absentes du volume y sont copiées (« Photos du catalogue : 291 sur le volume, dont N copiées depuis l'image »). Sur le volume, elles survivent aux redéploiements et aux remises à zéro.
- **Arrière-plan** : le programme `photos` de supervisord télécharge celles qui manquent encore (seulement celles-là, 20 s au plus par essai, 3 essais, 10 min au plus en tout, arrêt après 5 échecs d'affilée), puis `app:yack:catalog:photos` les rattache aux produits chargés avec le visuel de repli. Seule l'image du produit change : ni prix, ni stock, ni commande, ni compte ; une image choisie dans le back-office est gardée ; relancer ne change rien. Le contrôle de santé n'attend pas ce passage.

Ce que montrent les journaux du déploiement (onglet Deploy Logs), passage complet :

```
Photos du catalogue : 291 sur le volume, dont 0 copiées depuis l'image
photos : passage en arrière-plan sur /data/photos
photos présentes : 291, téléchargées : 0, échecs : 0, non tentées : 0 (sur 291, en 0 s)
photos rattachées aux produits : 0, déjà en place : 291, sans photo sur le disque : 41
photos : passage terminé
```

Les 41 produits « sans photo sur le disque » sont les pièces détachées, qui n'ont que leur visuel générique. Un nombre plus grand, des lignes `échec <référence> : <cause>` ou `photos : arrêt, CDN injoignable ou bloqué` disent qu'il manque des photos : un simple redémarrage du service (Restart) refait le passage, sans remise à zéro. Une cause `HTTP 403` pour chaque photo désigne un CDN ou un réseau qui refuse l'accès depuis Railway.

Réglages facultatifs du passage, en variables du service : `YACK_PHOTOS_DELAI` (secondes par essai), `YACK_PHOTOS_ESSAIS`, `YACK_PHOTOS_BUDGET` (secondes en tout), `YACK_PHOTOS_ABANDON` (échecs d'affilée avant l'arrêt, une photo absente du CDN ne compte pas).

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
| `RAILWAY_DOCKERFILE_PATH` | `docker/railway/Dockerfile` |

Service `mailpit` : `MP_SMTP_BIND_ADDR=[::]:1025`, `MP_UI_BIND_ADDR=[::]:8025`, `PORT=8025`, `MP_UI_AUTH=demo:<mot de passe>`.

## Réglages du service `yack-app`

Railway ne lit pas de fichier de configuration (`railway.json`) pour un service créé après août 2026 : les réglages sont portés par le service. Builder Dockerfile, chemin `docker/railway/Dockerfile` (variable `RAILWAY_DOCKERFILE_PATH`), contrôle de santé `/healthz` avec 900 s d'attente, redémarrage en cas d'échec (3 essais), volume sur `/data`, domaine sur le port 8080.

## Déployer

Depuis la racine du dépôt, CLI Railway liée au projet (`railway link`) : `railway up --service yack-app`. La CLI envoie le dossier en suivant `.gitignore` (ni `vendor/`, ni `node_modules/`, ni `var/`, ni `.env.local`) ; Railway construit l'image et la démarre. Le dépôt GitLab n'intervient pas.

Avant d'envoyer, on peut construire l'image en local : `docker build -f docker/railway/Dockerfile -t yack-railway .`

## Manuels et notices (PDF)

Les PDF des fabricants ne sont ni dans le dépôt ni dans l'image : ils vivent sur le volume (`/data/manuels`), remplis depuis leurs liens officiels, avec l'accord de Yack avant toute mise en production (ADR 0008).

## Limites

- Démo seulement : une instance, dépendances PHP de développement installées, `APP_ENV=prod`, pas de worker Messenger (comme la stack locale).
- Meilisearch n'est pas déployé : la recherche passe par la base.
