# Yack front (SvelteKit 3, headless)

Boutique Clik&Yack et builder CMS en SvelteKit (Svelte 5 runes, TypeScript strict, adapter-node).
Sylius ne sert plus que l'admin (`/admin`), l'API (`/api/v2`), les médias et les assets : nginx
(`etc/nginx/default.conf.template`) envoie tout le reste à ce front.

```
navigateur ──► nginx :HTTP_PORT ─┬─ /admin, /api, /media, /bundles, /build, /_profiler, /_wdt, fichiers de public/ ──► PHP (Sylius)
                                 └─ le reste ──► front:3000 (SSR SvelteKit) ──► http://nginx/api/v2 (BFF)
```

## Lancer

```sh
make yack-reset                                  # base + fixtures + clés JWT + assets + npm ci du front
make yack-up                                     # tout le stack sur http://localhost:8088 (vite dev, HMR)
FRONT_MODE=prod make yack-up                     # front : npm run build && node build
docker compose exec front npm run check          # svelte-check (0 erreur attendu)
```

Les anciennes URL de la boutique Twig (`/fr_FR/…`) répondent par une redirection 301 vers la route équivalente
(`src/lib/server/legacy.ts`, hook `legacy` de `src/hooks.server.ts`) : accueil, fiches produit, catégories (slug Sylius →
`/produits/…` ou `/pieces/…`), panier, connexion, inscription, liens d'activation / de mot de passe, compte, SAV, installateurs.
Une ancienne URL sans équivalent finit en 404.

Variable : `SYLIUS_API_URL` (Sylius vu du serveur SvelteKit, `http://nginx` en Docker), déclarée dans
`src/env.ts` et lue via `$app/env/private`. `ORIGIN` est fournie par compose pour `node build`.

## BFF

Le navigateur ne parle qu'au front : aucun JWT côté client.

| Cookie (httpOnly, SameSite=Lax) | Contenu |
|---|---|
| `yack_session` | JWT shop du client (`POST /api/v2/shop/customers/token`). Durée = expiration du JWT si « Rester connecté », sinon cookie de session. |
| `yack_cart` | `tokenValue` du panier ECOM (30 jours). Repris à la connexion (`POST /shop/orders` rend le dernier panier non vide du client) ; ignoré et effacé s'il ne pointe plus sur un panier (`state` ≠ `cart`). |
| `yack_registered` | e-mail et profil de l'inscription qui vient d'aboutir (path `/inscription`, 30 min), pour la page « vérifiez vos e-mails ». |
| `yack_cms_token` | JWT admin du builder (`/cms-admin`). |

- `src/hooks.server.ts` lit les cookies et charge `GET /api/v2/shop/yack/me` dans `locals.viewer`
  (visiteur = constante `VISITOR`, sans appel). Un 401 ferme la session.
- `#lib/server/sylius.ts` : client typé. `sylius(event)` transmet le JWT ; sur 401 il ferme la session et
  redirige vers `/connexion?redirect=…&expire=1`. `ApiError` porte `status`, `message` et `violations`
  (422 API Platform).
- Les données et mutations passent par des remote functions (`#lib/remote/*.remote.ts`, section suivante) ;
  il n'y a plus de `load` au niveau du layout racine. Les routes pas encore migrées (compte, tunnel,
  panier, SAV, installateurs, builder) gardent leurs `+page.server.ts` (load + form actions `use:enhance`).

## Remote functions

Activées dans `vite.config.ts` : `experimental.remoteFunctions` (SvelteKit) et
`compilerOptions.experimental.async` (Svelte, `await` dans les composants). Référence : types de `$app/server`
dans `node_modules/@sveltejs/kit/types/index.d.ts`.

### Fichiers

- Un fichier par domaine : `src/lib/remote/<domaine>.remote.ts` (`catalog`, `cms`, `session`, `cart`, puis
  `account`, `checkout`, `sav`, `installers`, `builder`). Import : `#lib/remote/<domaine>.remote.js`.
- Un `.remote.ts` n'exporte **que** des remote functions (contrainte SvelteKit) : schémas et helpers restent
  privés au fichier ou vivent dans `#lib/server/*`.
- Ce sont des adaptateurs minces : la logique (appels Sylius, mapping, règles) reste dans `#lib/server/*`, qui
  prend un `ShopEvent` (`#lib/server/sylius.ts` : `locals`, `cookies`, `url`, `request`, `isRemoteRequest`).
  Dans une remote function : `getRequestEvent()` (`$app/server`) donne cet event.
- Les 404 / redirections se lèvent **dans la query** (`error(404, …)`, `redirect(…)`) : en SSR la redirection
  devient une réponse 30x et l'erreur rend le `+error.svelte` le plus proche avec son statut.
- `event.url` vaut l'URL de la page pour `form` et `command`, mais l'URL technique `/_app/remote/…` pour une
  `query` appelée depuis le navigateur : pour construire un lien de retour, utiliser `pageUrl(event)`
  (`#lib/server/session.ts`, déjà fait par `sylius(event)` sur un 401) ou passer l'information en argument.

### Validation : valibot

Les arguments d'une `query`/`command` et les champs d'un `form` sont validés par un schéma Standard Schema.
Aucune bibliothèque de ce type n'était présente (seulement les types `@standard-schema/spec`) : `valibot`
(sans dépendance, tree-shakable, utilisée par la doc SvelteKit) est le choix retenu. Il infère les types
d'entrée exigés par `form.fields`. Une query sans argument n'a pas de schéma.

### Exemples

```ts
// src/lib/remote/exemple.remote.ts
import { error, invalid } from '@sveltejs/kit';
import { command, form, getRequestEvent, prerender, query } from '$app/server';
import * as v from 'valibot';
import { ApiError, sylius } from '#lib/server/sylius.js';

/** Lecture : mise en cache par argument pendant la requête SSR et côté client (dédoublonnée). */
export const getThing = query(v.string(), async (code) => {
	const thing = await findThing(sylius(getRequestEvent()), code);
	if (!thing) error(404, 'Introuvable');
	return thing;
});

/** Mutation de formulaire : fonctionne sans JS (POST natif + re-rendu SSR), améliorée avec JS. */
export const saveThing = form(
	v.object({
		code: v.pipe(v.string(), v.nonEmpty('Code obligatoire.')),
		quantity: v.pipe(v.number(), v.integer(), v.minValue(1, 'Quantité ≥ 1.')),
		urgent: v.optional(v.boolean(), false) // booléen d'un form : toujours optionnel (case décochée = absent)
	}),
	async ({ code, quantity }) => {
		try {
			await sylius(getRequestEvent()).post('/…', { code, quantity });
		} catch (e) {
			if (e instanceof ApiError && e.status < 500) invalid(e.message); // message affiché, pas d'erreur 500
			throw e;
		}
		await getThing(code).refresh(); // queries touchées, renvoyées avec la réponse
		return { saved: quantity }; // → saveThing.result
	}
);

/** Action programmatique (pas de formulaire, JS obligatoire) : bouton « favori », tri par glisser-déposer… */
export const toggleFavorite = command(v.string(), async (code) => {
	await sylius(getRequestEvent()).post('/…', { code });
	getThing(code).refresh();
});

/** Données figées au build (aucune ici : tout dépend du profil ou du CMS publié). */
export const getLegalLinks = prerender(() => [{ label: 'CGV', href: '/cgv' }]);
```

```svelte
<script lang="ts">
	import { getThing, saveThing, toggleFavorite } from '#lib/remote/exemple.remote.js';
	let { params } = $props();
	const thing = $derived(await getThing(params.code)); // SSR complet, navigation = attend comme un load
	const error = $derived(saveThing.fields.allIssues()?.[0]?.message);
</script>

<form {...saveThing}>
	<input {...saveThing.fields.code.as('hidden', thing.code)} />
	<input {...saveThing.fields.quantity.as('number', 1)} />
	<button disabled={saveThing.pending > 0}>Enregistrer</button>
</form>
{#if error}<div class="alert alert-danger" role="alert">{error}</div>{/if}
<button onclick={() => toggleFavorite(thing.code)}>Favori</button>
```

### Règles

- **Contenu principal** : `$derived(await query(...))` dans le script ou `{await …}` dans le markup, **sans**
  `<svelte:boundary pending>` : le SSR rend tout (vérifier par `curl`), et une navigation attend les données
  comme avec un `load`.
- **Contenu secondaire** (« Derniers produits »…) : l'envelopper dans `#lib/layout/Deferred.svelte` avec un
  snippet `pending` (squelette, ex. `#lib/catalog/ProductGridSkeleton.svelte`). Attention : en SSR, un
  `<svelte:boundary>` doté d'un `pending` ne rend **que** le `pending` ; `Deferred` ne le fournit que dans le
  navigateur, le SSR rend donc le contenu complet.
- Chargement d'une même page avec d'autres paramètres : `$effect.pending()` (ex. grille grisée dans `Listing.svelte`).
- **Formulaires** : `<form {...monForm}>` ; champs par `monForm.fields.x.as(type, défaut?)` (fournit `name`,
  `value`, `aria-invalid`) ; erreurs `fields.x.issues()` / `fields.allIssues()` (schéma et `invalid(…)`) ;
  `monForm.result` = valeur retournée ; `monForm.pending` pour désactiver le bouton.
  Plusieurs instances d'un même formulaire sur une page (lignes de liste) : `monForm.for(id)`.
  Le `enhance` par défaut **vide le formulaire** après succès ; pour garder les valeurs :
  `{...monForm.enhance(async ({ submit }) => void (await submit()))}`.
- Noms de champs : préfixe `_` (ex. `_password`) = jamais renvoyé dans la page après une erreur ; les types
  `number`/`checkbox` de `.as()` préfixent le `name` (`n:`, `b:`) pour la conversion, y compris sans JS.
- **Rafraîchissement** après mutation : côté serveur, `query(arg).refresh()` ou `.set(valeur)` → seules ces
  queries sont mises à jour ; sans cela, le client rafraîchit **toutes** les queries de la page. Côté client,
  mise à jour optimiste : `submit().updates(query.withOverride(…))`. Une `redirect` recharge tout.
- **Compteur panier du header** : `getCartSummary` (`#lib/remote/cart.remote.ts`, `CartSummary | null`). Toute
  mutation du panier fait `getCartSummary().set(panier)` (ou `.refresh()`) côté serveur. Les form actions encore
  en `use:enhance` (panier, SAV) le rafraîchissent via `invalidateAll()`.
- **Session** (`#lib/remote/session.remote.ts`) : `getViewer()` (profil courant, `locals.viewer` lu par
  `hooks.server.ts`), formulaires `login` et `logout` (`logout.for('…')` s'il y en a plusieurs sur la page).
  `/connexion` garde un `+page.server.ts` réduit à la redirection de garde (client déjà connecté).
- Gardes (`/compte/**`, builder) : `+layout.server.ts` / hooks restent autorisés pour les redirections.

## Routes

| URL | Contenu |
|---|---|
| `/` | page CMS `accueil` + « Derniers produits » |
| `/[slug]` | pages CMS publiées (`?apercu=1` : brouillon, session builder) |
| `/produits/…`, `/pieces/…` | listings par taxon (Clik&Yack / Clik&Yack Parts) : `?q=`, `tri=`, `par=9/18/27`, `page=` |
| `/recherche?q=` | recherche (nom + code exact) |
| `/produit/[slug]` | fiche produit : prix, stock et panier pour les comptes ECOM seulement |
| `/sav` → `/sav/[n° de série]` | SAV : recherche (n° de série ou ServiceCode, `?serial=` redirigé), garantie et modèles |
| `/sav/[série]/[ServiceCode]`, `…/[vue]` | vues éclatées ; repères ↔ tableau des pièces, prix et `?/addToCart` (même panier) pour ECOM |
| `/panier` | panier ECOM : vignettes, quantités (`?/update`, 0 = retrait), `?/remove`, totaux HT/TVA/TTC ; visiteur → connexion, PRO → explication |
| `/commande/adresse` → `/livraison` → `/paiement` → `/recapitulatif` | tunnel (`#lib/server/checkout.ts`) : carnet d'adresses ou nouvelle adresse, « facturation identique », transporteur / agence, 3 moyens de paiement (`?/select` recalcule l'escompte), CGV ; une étape non atteinte renvoie à la bonne étape |
| `/commande/confirmation/[token]` | commande validée : RIB, référence et échéance (virement) ou « paiement accepté » (carte) |
| `/trouver-un-installateur` | annuaire : `?secteur=&gamme=&activite=`, liste + carte Leaflet (CDN unpkg) |
| `/connexion` | session client : formulaires `login` / `logout` (`#lib/remote/session.remote.ts`) |
| `/inscription` → `/inscription/terminee` | inscription en 4 étapes (profil PRO/ECOM, société avec contrôle SIRET/TVA, contact, adresses), barre de progression ; validation par étape dans le navigateur (`#lib/account/registration.ts`, mêmes messages que l'API) ou par POST `?/etape` sans JS ; les `422` de l'API ramènent à l'étape du champ |
| `/activation/[token]`, `/mot-de-passe/oublie`, `/mot-de-passe/reinitialiser/[token]` | liens des e-mails : activation, mot de passe oublié / nouveau mot de passe |
| `/compte` | tableau de bord : profil (badge PRO/ECOM, société), dernières commandes (ECOM) ou état de la demande d'ouverture (PRO). Tout `/compte/**` exige une session (`requireCustomer`, `#lib/server/account.ts`) |
| `/compte/profil`, `/compte/adresses` (`/nouvelle`, `/[id]`) | informations + mot de passe (`#mot-de-passe`) ; carnet d'adresses (ajout, modification, par défaut, suppression) |
| `/compte/commandes`, `/compte/commandes/[n°]` | historique et détail (statuts, modalités de virement, n° de suivi, lignes, escompte, totaux) ; PRO → explication + lien vers la demande |
| `/compte/acces-commande` | PRO : demande d'ouverture à la commande pré-remplie (`/yack/order-access-requests/draft`), puis « demande en cours » ; ECOM : commande ouverte |
| `/cms-admin/login`, `/cms-admin/builder/[slug]` | builder GrapesJS (lien depuis l'admin Sylius) |

Slugs CMS réservés : `CmsPage::RESERVED_SLUGS` côté Sylius.

Non portés depuis l'ancienne boutique Twig (supprimée) : avis/notes produits, liste de souhaits, sélecteur de langue (FR seul).
