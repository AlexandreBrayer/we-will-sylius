# ADR 0005 : fichiers téléversés (justificatifs, photos, devis, médias)

- Statut : proposé (lot 1, ticket L0-08, à valider par Thomas et Alexandre)
- Contexte : le Kbis à l'inscription PRO (lot 1), puis la médiathèque du CMS, les photos de plaque et les devis PDF (lot 2) font passer des fichiers du navigateur à Sylius. Certains contiennent des données personnelles (Kbis : identité du dirigeant).

## Décision

### Un seul chemin

```
navigateur ──(multipart, formulaire distant)──► nginx ──► front SvelteKit ──(multipart + JWT)──► nginx ──► Sylius (API)
```

1. Le navigateur envoie le fichier par un **formulaire distant** SvelteKit (`form` de `$app/server`, champ `<input {...monForm.fields.x.as('file')}>`, `enctype="multipart/form-data"`). Le type `RemoteFormInput` accepte `File`.
2. Le schéma valibot du champ est `uploadField(extensions)` de `front/src/lib/server/upload.ts` : fichier présent, non vide, taille maximale, extension attendue. Message affiché : « Fichier trop volumineux (20 Mo maximum) », « Format non accepté (PDF, JPG, JPEG, PNG). » Ce contrôle évite un appel inutile et donne un message en français ; **l'API reste juge**.
3. Le front transmet le fichier à l'API par `ShopClient.postForm(path, FormData)` (`front/src/lib/server/sylius.ts`) : corps `FormData` envoyé tel quel, sans `Content-Type` forcé (fetch écrit `multipart/form-data` et sa frontière), JWT du client en `Authorization`, mêmes `ApiError` que les appels JSON. `await uploadFormData(champs, fichiers)` construit le corps, chaque fichier lu en entier : un formulaire distant envoyé avec JavaScript fournit un fichier lu au fil de la requête, que le `FormData` de fetch enverrait vide.
4. Sylius vérifie à nouveau : droits, taille, **type lu sur le contenu** (`fileinfo`, `finfo_file()`, jamais l'extension ni le `Content-Type` envoyé), puis stocke.

### Stockage

| Fichiers | Où | Servis par |
|---|---|---|
| Médias publics du CMS (images, PDF publics) | `public/media/yack-cms/` | nginx (`/media/`) |
| Justificatifs (Kbis), photos de plaque, devis | `var/yack/private/<contexte>/` (ex. `var/yack/private/account/`) | un contrôleur Symfony qui vérifie les droits |

- Fichier privé : **nom aléatoire** (ex. `bin2hex(random_bytes(16))` suivi de l'extension déduite du type réel), jamais le nom d'origine dans le chemin. Le nom d'origine, s'il est utile, est une donnée en base.
- Réponse du contrôleur : `X-Content-Type-Options: nosniff`, `Content-Disposition: attachment`, `Content-Type` issu du type vérifié à l'enregistrement.
- `make yack-reset` supprime `var/yack/private/` (fichiers orphelins une fois la base recréée). Recette uniquement avec des fichiers `FICTIF` (le clone du Mac est sous `~/Documents`, possiblement synchronisé avec iCloud).

### Limites, couche par couche

| Couche | Réglage | Valeur | Où |
|---|---|---|---|
| nginx (entrée unique) | `client_max_body_size` | 24m (corps entier : fichier de 20 Mio et enveloppe multipart) | `etc/nginx/default.conf.template` |
| front, `FRONT_MODE=prod` (adapter-node) | `BODY_SIZE_LIMIT` | 24M (512K par défaut) | `compose.yml`, service `front` |
| front, `FRONT_MODE=dev` (vite) | aucune | | |
| front, contrôle applicatif | `UPLOAD_MAX_BYTES` | 20 Mio par fichier | `front/src/lib/server/upload.ts` |
| PHP | `upload_max_filesize`, `post_max_size` | 20M et 24M (l'image `ghcr.io/sylius/sylius-php` fixe 5M et 6M) | `etc/php/yack-uploads.ini`, monté par `compose.yml` dans `conf.d` du service `php` |
| API (métier) | constante du cas d'usage | 20 Mio par fichier (ex. `NameplatePhoto::MAX_BYTES`) | `src/Yack/<Contexte>/Domain` |

Un cas d'usage peut fixer une limite plus basse (`uploadField(extensions, maxBytes)`), jamais plus haute. Un fichier de 20 Mio passe donc toutes les couches ; au-delà, le contrôle du front répond en français avant l'API, et nginx ne coupe qu'au-delà de 24 Mio.

## Conséquences

- Au-delà de 24 Mio, la requête est refusée **avant** l'API par nginx (413), et en `FRONT_MODE=prod` aussi par adapter-node (413) : le message en français de `upload.ts` n'est alors pas affiché. Pour l'afficher sans envoyer le fichier, le composant contrôle le fichier dans le navigateur avec des règles importables côté client, hors de `src/lib/server/` (exemple : `photoIssue()` de `front/src/lib/sav/nameplate.ts`, partagée par le navigateur et le formulaire distant).
- L'enveloppe multipart s'ajoute à la taille du fichier : nginx, `BODY_SIZE_LIMIT` et `post_max_size` gardent 4 Mio au-dessus de la limite des fichiers.
- Photo prise au téléphone : le navigateur la réduit avant l'envoi (1 600 px, JPEG ; `front/src/lib/sav/photo.ts`), elle part en quelques centaines de Ko ; sans JavaScript, le fichier part tel quel, dans les limites ci-dessus.
- `FRONT_MODE=prod` exige `PROTOCOL_HEADER=x-forwarded-proto` (compose.yml) : sans lui, adapter-node prend `https` pour origine et refuse tout POST de formulaire du navigateur (403, contrôle CSRF de SvelteKit). L'hôte vient de l'en-tête `Host` transmis par nginx : l'IP du Mac (téléphone sur le Wi-Fi) passe le même contrôle que `localhost`.
