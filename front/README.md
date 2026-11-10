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
`src/env.ts` et lue via `$app/env/private`. `node build` n'a pas d'origine fixe : il la déduit de chaque requête
(`PROTOCOL_HEADER=x-forwarded-proto` et en-tête `Host` transmis par nginx, `compose.yml`), ce qui garde le contrôle
CSRF des formulaires actif aussi bien sur `http://localhost:<port>` que sur `http://<IP du Mac>:<port>` (téléphone).

## BFF

Le navigateur ne parle qu'au front : aucun JWT côté client.

| Cookie (httpOnly, SameSite=Lax) | Contenu |
|---|---|
| `yack_session` | JWT shop du client (`POST /api/v2/shop/customers/token`). Durée = expiration du JWT si « Rester connecté », sinon cookie de session. |
| `yack_cart` | `tokenValue` du panier ECOM (30 jours). Repris à la connexion (`POST /shop/orders` rend le dernier panier non vide du client) ; effacé dans la réponse qui valide la commande ; ignoré s'il ne pointe plus sur un panier (`state` ≠ `cart`) puis remplacé au prochain ajout (une query ne peut pas écrire de cookie). |
| `yack_registered` | e-mail et profil de l'inscription qui vient d'aboutir (path `/` : la query `getRegistered` est appelée sur `/_app/remote/…` ; 30 min), pour la page « vérifiez vos e-mails ». |
| `yack_cms_token` | JWT admin du builder (`/cms-admin`). |

- `src/hooks.server.ts` lit les cookies et charge `GET /api/v2/shop/yack/me` dans `locals.viewer`
  (visiteur = constante `VISITOR`, sans appel). Un 401 ferme la session.
- `#lib/server/sylius.ts` : client typé. `sylius(event)` transmet le JWT ; sur 401 il ferme la session et
  redirige vers `/connexion?redirect=…&expire=1`. `ApiError` porte `status`, `message` et `violations`
  (422 API Platform).
- Les données et mutations passent par des remote functions (`#lib/remote/*.remote.ts`, section suivante) ;
  il n'y a plus de `load` au niveau du layout racine ni de form action. Restent trois fichiers serveur de
  routage pur, sans données (voir « Gardes » plus bas).

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
  Instance de la visite : `monForm.for('<page>')` appelé dans le script de la page, supprimée au démontage,
  sinon `result` (« enregistré ») et les erreurs du module reviennent à la visite suivante (espace client).
  Le `enhance` par défaut **vide le formulaire** après succès ; pour garder les valeurs :
  `{...monForm.enhance(async ({ submit }) => void (await submit()))}`.
- **Fichier dans un formulaire distant** : `enctype="multipart/form-data"` sur le `<form>` (sans JS, SvelteKit ne
  l'ajoute pas), champ `{...monForm.fields.x.as('file')}`, schéma valibot côté serveur, envoi à l'API par
  `ShopClient.postForm` + `uploadFormData`. Un fichier préparé dans le navigateur (photo réduite) remplace celui du
  champ par `DataTransfer`, puis un événement `input` resynchronise le formulaire (exemple : `#lib/sav/photo.ts` et
  `#lib/sav/PhotoPicker.svelte`, prise de photo commune à `/sav` et `/assistance`).
- Noms de champs : préfixe `_` (ex. `_password`) = jamais renvoyé dans la page après une erreur ; les types
  `number`/`checkbox` de `.as()` préfixent le `name` (`n:`, `b:`) pour la conversion, y compris sans JS.
- **Rafraîchissement** après mutation : côté serveur, `query(arg).refresh()` ou `.set(valeur)` → seules ces
  queries sont mises à jour ; sans cela, le client rafraîchit **toutes** les queries de la page. Côté client,
  mise à jour optimiste : `submit().updates(query.withOverride(…))`. Une `redirect` recharge tout.
- **Compteur panier du header** : `getCartSummary` (`#lib/remote/cart.remote.ts`, `CartSummary | null`). Toute
  mutation du panier fait `getCartSummary().set(panier)` (ou `.refresh()`) côté serveur (`addToCart`,
  `updateCart`, `placeOrder`). Un `form` qui redirige sans refresh/set explicite fait rafraîchir **toutes** les
  queries de la page quittée (une query gardée y redirigerait à son tour) : les étapes du tunnel rafraîchissent
  la query de l'étape suivante avant leur `redirect`.
- **Queries** : ni `event.url` (même en SSR) ni écriture de cookie ; `pageUrl(event)` donne l'URL de la page.
- **Session** (`#lib/remote/session.remote.ts`) : `getViewer()` (profil courant, `locals.viewer` lu par
  `hooks.server.ts`), formulaires `login` et `logout` (`logout.for('…')` s'il y en a plusieurs sur la page).
  `/connexion` garde un `+page.server.ts` réduit à la redirection de garde (client déjà connecté).
- **Builder** : une seule connexion, celle du back-office. `/admin/yack/builder?vers=<chemin>`
  (`BuilderAccessController`, pare-feu admin) pose le cookie `yack_cms_token` avec le jeton API de
  l'administrateur connecté, puis redirige vers le chemin demandé ; sans session admin, le formulaire de
  connexion de l'admin passe d'abord. `/cms-admin/login` ne fait que rediriger vers cette adresse ;
  `/cms-admin/logout` (POST) retire le cookie et revient à l'admin.
- **Gardes** : redirections avant rendu, gardées hors remote functions parce qu'une query ne peut pas lire
  `event.url` ni rediriger avant que la page soit rendue (elle redirige au moment où elle est attendue) :
  `compte/+layout.server.ts` (tout `/compte/**` sans session → `/connexion?redirect=…`, avant toute query),
  `connexion/+page.server.ts` (client déjà connecté → `?redirect=`), `cms-admin/+page.server.ts` (307 vers
  `/cms-admin/builder`) ; le hook `hooks.server.ts` protège `/cms-admin/**` (hors login).

## Routes

| URL | Contenu |
|---|---|
| `/` | page CMS `accueil` + « Derniers produits » |
| `/[slug]` | pages CMS publiées (`?apercu=1` : brouillon, session builder) |
| `/produits/…`, `/pieces/…` | page catégorie rendue par son template CMS (`categorie-*` : en-tête, sous-catégories, `categorie-produits` = filtres, tri, pagination en SSR : `?q=`, `tri=`, `par=9/18/27`, `page=`) ; `?apercu=<id>` : autre template / brouillon (session builder, bandeau, `noindex`) |
| `/recherche?q=` | recherche par pertinence (API de recherche, `#lib/server/search.ts`) ; suggestions dans l'en-tête (`SearchSuggest.svelte`) |
| `/produit/[slug]` | fiche produit rendue par son template CMS (`produit-*` ; `produit-achat` : prix, stock et panier pour les comptes ECOM seulement) ; `?apercu=<id>` comme ci-dessus |
| `/sav` → `/sav/[n° de série]` | SAV : recherche (n° de série ou ServiceCode, `?serial=` redirigé), garantie et modèles ; encart « La pièce par une photo » (`#lib/sav/NameplatePanel.svelte` avec `PhotoPicker.svelte`, formulaire distant `identifyFromPhoto`, ancre `#photo-plaque` de l'onglet SAV de la barre mobile) : n° du parc vers `/sav/<n°>`, référence du catalogue vers sa fiche `/produit/<slug>` ; sous l'encart, lien « Trouver le manuel d'un appareil » vers `/assistance` (`#lib/assistance/AssistanceLink.svelte`, absent si `YACK_ASSISTANCE=0`) |
| `/sav/[série]/[ServiceCode]`, `…/[vue]` | vues éclatées ; repères ↔ tableau des pièces, prix et `addToCart.for(variante)` (`cart.remote.ts`, même panier, compteur du header) pour ECOM (`sav.remote.ts`) |
| `/assistance` | Manuels et notices officiels d'un appareil (`#lib/assistance`, `assistance.remote.ts` → `#lib/server/assistance.ts`) : barre « Référence, modèle ou n° de série » en formulaire GET (`?q=`, URL partageable, sans JavaScript) ; `?ref=&sn=&marque=` après validation ; bandeau du niveau (exact, série, famille repliée, aucun avec lien `/contact?sujet=`), choix du modèle quand la saisie est un nom, liens vers les PDF officiels en nouvel onglet, `noindex` sur les résultats ; page vide : encart photo sous la barre (`PlateCapture`, `PhotoPicker`, formulaire distant `readPlate`, ancre `#photo-plaque`) (résultats affichés : lien discret « Nouvelle photo » vers `/assistance#photo-plaque`) dont la lecture ouvre l'écran « C'est bien ça ? » au-dessus de la barre (`PlateValidation` : marque, référence, n° de série corrigibles, origine de chaque valeur, désignation lue ; plaque illisible ou lecture indisponible : même écran, champs vides) ; « C'est bien ça, voir les manuels » mène à `?ref=&sn=&marque=`, « Reprendre la photo » rouvre l'encart ; sans JavaScript, le fichier part tel quel, l'écran est rendu par le serveur et une validation vide y revient avec le message ; sous les résultats, « Une question sur ce modèle ? » si un document exact ou série a son texte indexé (`askable`), sinon une phrase le dit (`ManualQuestion`, formulaire distant `askManuals`, marche sans JavaScript) : comptes connectés seulement (visiteur : lien de connexion qui revient sur la même URL), réponse en texte dans une zone `aria-live`, sources « Source : <titre>, page N » vers le PDF officiel à la page (`#page=N`, nouvel onglet), repère « démonstration » si la réponse est simulée, pages indexées sous le panneau ; `YACK_ASSISTANCE=0` : 404. Entrées : lien sous l'encart photo de `/sav`, « Manuels et notices » du menu *Nos services* (ordinateur et menu mobile, drapeau `assistanceEnabled` lu par `routes/+layout.svelte` et passé à `ShopHeader`), carte « Assistant documentation » de `/fonctions-intelligentes` ; module coupé : lien et entrée absents, carte sans lien. Pas d'onglet dans la barre mobile (6 onglets avec le panier ECOM ; l'onglet SAV mène à l'encart photo, le lien est juste dessous) |
| `/panier` | panier ECOM (`getCart`, `updateCart` : quantités `items[i]`, 0 = retrait, bouton `remove`) : vignettes, totaux HT/TVA/TTC ; visiteur → connexion, PRO → explication |
| `/commande/adresse` → `/livraison` → `/paiement` → `/recapitulatif` | tunnel (`checkout.remote.ts` → `#lib/server/checkout.ts`) : récapitulatif du layout `getCheckout(étape)` ; carnet d'adresses ou nouvelle adresse, « facturation identique », transporteur / agence, 3 moyens de paiement (le choix appelle la command `selectPaymentMethod` qui renvoie le récapitulatif recalculé ; sans JS, bouton « Actualiser » de `submitPayment`), CGV (`placeOrder`) ; une étape non atteinte renvoie à la bonne étape |
| `/commande/confirmation/[token]` | commande validée (`getConfirmedOrder`) : RIB, référence et échéance (virement) ou « Payé par carte Visa •••• 4242 » (carte) ; une commande carte non payée renvoie au règlement |
| `/commande/retour-paiement/[token]` | retour du prestataire de paiement (`+server.ts`) : applique le résultat, puis confirmation (acceptée) ou règlement (refusée, annulée) |
| `/commande/reglement/[token]` | « Paiement non abouti » (`payment.remote.ts` : `getPayableOrder`, `payByCard`, `changePaymentMethod`) : nouvel essai par carte (carte enregistrée ou nouvelle) ou bascule vers un virement, totaux recalculés |
| `/paiement-securise/[session]` | page de paiement hébergée simulée (Monext / Payline, layout propre, `#lib/payment/simulator.css`) : carte (marque, Luhn, expiration, cryptogramme contrôlés côté serveur, numéro jamais transmis à Sylius), 3-D Secure, abandon, cartes de test |
| `/trouver-un-installateur` | annuaire : `?secteur=&gamme=&activite=`, liste + carte Leaflet (CDN unpkg) ; filtres = navigation GET (URL partageable, SSR), seule `getInstallers` (`installers.remote.ts`) est relancée |
| `/connexion` | session client : formulaires `login` / `logout` (`#lib/remote/session.remote.ts`) |
| `/inscription` → `/inscription/terminee` | inscription en 4 étapes (profil PRO/ECOM, société avec contrôle SIRET/TVA, contact, adresses), barre de progression ; un seul formulaire `register` (`#lib/remote/registration.remote.ts`) : validation par étape dans le navigateur (`#lib/account/registration.ts`, mêmes messages que l'API), sans JS chaque bouton poste son `intent` (`etape`, `precedent`, `creer`) ; les `422` de l'API ramènent à l'étape du champ |
| `/activation/[token]`, `/mot-de-passe/oublie`, `/mot-de-passe/reinitialiser/[token]` | liens des e-mails : activation (query `activateAccount`), mot de passe oublié / nouveau mot de passe (`requestPasswordReset`, `resetPassword`), dans `registration.remote.ts` |
| `/compte` | tableau de bord : profil (badge PRO/ECOM, société), dernières commandes (ECOM) ou état de la demande d'ouverture (PRO). Tout `/compte/**` exige une session : garde `compte/+layout.server.ts` et chaque fonction de `#lib/remote/account.remote.ts` (`requireCustomer`, `#lib/server/account.ts`) |
| `/compte/profil`, `/compte/adresses` (`/nouvelle`, `/[id]`) | informations + mot de passe (`#mot-de-passe`, `updateProfile`, `changePassword`) ; carnet d'adresses : `saveAddress.for(id \| 'nouvelle')`, puis un seul formulaire `manageAddress` pour la liste (bouton `intent` = `defaut:id` / `supprimer:id`) |
| `/compte/cartes` | cartes enregistrées (`getSavedCards`, `removeSavedCard`) |
| `/compte/commandes`, `/compte/commandes/[n°]` | historique (`getOrders(?page)`) et détail (`getOrder(n°)` : statuts, modalités de virement, n° de suivi, lignes, escompte, totaux) ; PRO → explication + lien vers la demande |
| `/compte/acces-commande` | PRO : demande d'ouverture à la commande pré-remplie (`getOrderAccess` ← `/yack/order-access-requests/draft`, `requestOrderAccess`), puis « demande en cours » ; ECOM : commande ouverte |
| `/cms-admin/builder`, `/cms-admin/builder/[slug]` | builder GrapesJS des pages, ouvert depuis l'admin Sylius (*Contenu → Ouvrir le builder*) : `builder.remote.ts` ; `/cms-admin/login` redirige vers l'admin |
| `/cms-admin/builder/templates`, `/cms-admin/builder/templates/[id]` | templates fiche produit / page catégorie : liste par type, création, duplication ; éditeur (palette filtrée par type, bloc obligatoire verrouillé, aperçu sur un produit / une catégorie au choix, « Voir comme » Visiteur / PRO / ECOM, onglet « Affectations » enregistré aussitôt) |

Slugs CMS réservés : `CmsPage::RESERVED_SLUGS` côté Sylius.

Templates CMS (`docs/API_FRONT.md` §11) : `#lib/blocks/TemplateView.svelte` rend le fil d'Ariane puis les blocs
résolus ; `#lib/blocks/layout.ts` (`layoutRuns`, `PRODUCT_ZONE`) regroupe les blocs produit consécutifs en une zone
à deux colonnes (`column` du registre : galerie / description / caractéristiques / documents à gauche ; infos /
achat / SAV à droite, collant). Les blocs liés (`#lib/blocks/bound/*`) n'ont pas de props de données : ils lisent
`product` / `taxon` / `viewer` dans le contexte (`#lib/blocks/context.ts`). Registre et palette :
`#lib/blocks/registry.ts` (`scope`, `mandatory`, `column`, `allowedBlocks(type)`). Le canvas étant une iframe, les
blocs n'utilisent que Bootstrap et les classes de la boutique.

La palette (onglet « Blocs ») est rendue par `BuilderShell.svelte` à partir de `paletteBlocks(type)` du registre ; les
blocs restent déclarés dans GrapesJS, le clic appelle leur `onClick` et le glisser passe par `editor.Blocks.startDrag` /
`endDrag`, comme « Mes sections » (`#lib/editor/palette.ts`). Rubriques dans l'ordre : « Mise en page » (dépliée à
l'ouverture : blocs de base de `LAYOUT_ORDER`, Titre, Texte, Bouton, Espace, Texte + image, puis Section et Colonnes),
blocs liés du template, « Blocs Yack », « Mes sections ». Un champ de recherche filtre les libellés de toutes les
rubriques (sans casse ni accents) et déplie celles qui ont un résultat ; Échap le vide. L'aide est sur le « ? » : un
clic ou le focus la montre, Échap la masque. Vignettes 64 x 40 à deux gris dans `#lib/editor/thumbs/thumbs.ts`.

Blocs de base (`Titre.svelte`, `Texte.svelte`, `Bouton.svelte`, `Espace.svelte`, contrat dans `docs/API_FRONT.md`
§11) : textes écrits sur place (`textEditor()` de `#lib/blocks/editable.ts`) ; le Texte rend un `<p>` par paragraphe
sur la boutique et un seul texte brut dans le canevas, pour que l'édition sur place garde les lignes vides ; le Bouton
passe par `resolveLink()` ; l'Espace n'est hachuré que dans le canevas (`.yack-cms-block--editor`). Un composant de bloc
lit son style par `#lib/blocks/styleContext.ts` (`titleTag()`, `rowAlignment()` de `#lib/blocks/style.ts`).

Canvas d'un template = la vraie page (WYSIWYG) : mêmes composants, même CSS, même zone produit et même chrome que
`routes/+layout.svelte` (`ShopHeader`, `ProfileStrip`, fil d'Ariane, `ShopFooter`, `TabBar`, inertes, montés autour
du wrapper par `chrome` de `initEditor` ; `DemoBar` et mini-panier exclus). Largeur
« Ordinateur » = 1440 px, réduite par le zoom GrapesJS pour tenir dans le canvas (`fitCanvas`) ; Tablette 820 px,
Mobile 390 px. Les zones produit sont des composants GrapesJS dérivés (`#lib/editor/zones.ts`) : construites depuis
la liste plate de blocs au chargement (`layoutRuns`, le `grapesData` d'un template est ignoré), chaque colonne
n'accepte que ses blocs, `normalizeZones` regroupe après chaque changement comme `TemplateView` (bloc produit hors
zone → zone voisine ou nouvelle, zones consécutives fusionnées, zones vides retirées ; même pas d'annulation que le
changement), et l'enregistrement aplatit (`flattenRuns`) : l'API garde `{type, props}[]`. « Voir comme » choisit le
viewer du contexte (`PREVIEW_VIEWERS`) et le paramètre `as` des requêtes du canvas (`getTemplateSample`,
`getBlockCatalog`, `getPreviewListing`) : données de la boutique en anonyme, prix et stock du canal pour ECOM lus par
l'API admin (`adminChannelPrice`, `YACK_WEB`) ; le formulaire « Ajouter au panier » est rendu mais sa soumission est
bloquée (aucun appel panier). L'historique (`revisionCanvas.ts`) et les sections (`sectionCanvas.ts`) construisent
et lisent les blocs dans leurs zones (`zonedComponents`, `pageComponents`) ; un titre de bloc produit saisi dans le
template se modifie en place (un texte par défaut, non).

Conteneurs de mise en page (`docs/API_FRONT.md` §11, « Conteneurs de mise en page ») : `section` (`children`) et
`colonnes` (`columns`, ratios 50/50, 33/66, 66/33, 3 × 33), groupe « Mise en page » de la palette. Module pur
`#lib/blocks/containers.ts` (constantes miroir de `BlockType` vérifiées par `test/containers.test.ts`, parcours de
l'arbre `flattenNodes` / `nodePaths`, chemin français « Section 1 › colonnes 2 › colonne 1 › bloc 1 », lecture de
l'API `readBlocks`) ; rendu `Section.svelte` / `Columns.svelte` via `BlockRenderer` (récursif, filtre d'audience à
chaque niveau), classes `yack-cms-layout*` / `yack-cms-cols*` de `assets/shop/styles/_cms-layout.scss` (variables
`--yl-*`, colonnes empilées sous 768 px, grilles internes en requêtes de conteneur). Canvas : `#lib/editor/layouts.ts`
(composants GrapesJS section, colonnes et `yack-colonne`, une colonne par part du ratio ; règles d'imbrication
`blockParents` : une colonne refuse sections, colonnes et blocs pleine largeur, une section refuse une section ; un
dépôt refusé par une colonne, depuis la palette ou en déplaçant un bloc du canvas, est annulé (GrapesJS le poserait
sinon dans la section ou la page autour) et le bandeau du builder en donne la raison (`DROP_REFUSED_EVENT`) ;
conversion récursive blocs ↔ composants pour le chargement, l'enregistrement, l'historique et les sections). Style
d'un conteneur : les seules propriétés de `CONTAINER_STYLE`, au niveau du compte (charte, libre avec avertissement,
libre).

Builder, onglet « Structure » (`#lib/editor/tree`, remplace le Layer Manager GrapesJS) : arbre des blocs branché
sur le modèle GrapesJS (sélection et survol synchronisés avec le canvas), glisser-déposer pour réordonner ou changer
de parent (règles d'imbrication des modèles via `Components.canMove`), monter / descendre / dupliquer / supprimer,
clavier (↑ ↓ Début Fin, Entrée sélectionne, Alt+↑ ↓ déplace, Suppr, Ctrl+D, Ctrl+Z / Ctrl+Maj+Z). Tout passe par le
modèle : l'UndoManager couvre chaque action. Bloc obligatoire d'un template (`yackMandatory`, non `removable` ni
`copyable`) : cadenas dans l'arbre et dans la barre d'outils du canvas, suppression / duplication refusées (boutons,
touche Suppr, barre d'outils) avec un message ; zones produit et colonnes : ni supprimées ni dupliquées, colonnes
fixes ; `installTree(editor)` à appeler après `initEditor`.

Non portés depuis l'ancienne boutique Twig (supprimée) : avis/notes produits, liste de souhaits, sélecteur de langue (FR seul).

## Habillage du builder

Le builder (`/cms-admin`) suit le design system de la maquette « YACK Seduction », avec les mêmes principes que le back-office Sylius : barre du haut blanche, fond gris clair, cartes blanches à filet gris 25 % et rayon 12 px, boutons et champs en pilule (champs magenta 10 %, liseré et halo magenta au focus), Lato, magenta en accent. Le panneau du bloc et la palette suivent la mini-charte de l'outil (plus bas), dense et neutre. Pastilles : turquoise = réussi ou publié, jaune = attention (verrou d'édition), orange d'accent = erreur (la charte n'a pas de rouge).

- **Tokens** : `src/lib/admin/admin.css` (`:root`, variables `--yack-*`). Le builder ne charge pas la feuille de la boutique (Bootstrap n'a rien à faire dans l'éditeur) : il recopie les tokens de `assets/shop/styles/_tokens.scss`, source unique. `test/builder-tokens.test.ts` compare chaque copie à `_tokens.scss` et interdit toute couleur ou police en dur dans les composants du builder. Une nouvelle couleur : token SCSS d'abord, puis variable ici.
- **Police** : Lato, déclarée dans `admin.css`, servie par nginx depuis `/build/app/shop/fonts/` (copie sans empreinte faite par Encore, `webpack.config.js`). Après un changement du `webpack.config.js` ou sur un poste neuf : `make yack-reset`, ou `docker compose run --rm nodejs "npx encore dev --config-name app.shop"`.
- **Classes partagées** (`admin.css`) : `.yb-btn` (`-primary`, `-dark` = secondaire turquoise, `-outline`), `.yb-badge`, `.yb-field`, `.yb-error`, `.yb-wrap` / `.yb-card` / `.yb-table` (écrans de liste), `.yb-segmented` (aperçu, « Voir comme »).
- **Éditeur** : `BuilderShell.svelte` (barre, panneaux, onglets, bandeaux, variables `--gjs-*` de GrapesJS passées au magenta, champs pilule des panneaux de réglage de la page ou du template, `.shell-panel`), `panels/`, `traits/` et `tree/` pour le détail.

### Mini-charte de l'outil

La charte Yack habille les pages ; le panneau du bloc et la palette ont leur mini-charte (ADR 0010).

- **Maquette de référence** : `front/static/ui-kit/panneau.html` (page autonome, servie à `/ui-kit/panneau.html`). Elle montre la palette ouverte, le panneau Style d'un texte + image et les états des contrôles ; elle fait foi pour tout ce qui se voit dans l'éditeur.
- **Variables** : `#lib/editor/ui-tokens.css`, variables `--ui-*` (gris, accent, typographie 11 / 12 / 13 px, grille de 4 px, contrôle 28 px, pastille 20 px, rayon 4 px, colonne de 300 px) construites uniquement à partir des tokens `--yack-*` de `#lib/admin/admin.css` et de tailles. `test/builder-tokens.test.ts` vérifie que la maquette et `ui-tokens.css` déclarent les mêmes variables, qu'aucune couleur n'est écrite en dur (noms de couleur CSS compris) et que les codes des pastilles de la maquette suivent les tokens.
- **Classes** : `#lib/editor/ui-kit.css`, mêmes noms que la maquette (`ui-row`, `ui-seg`, `ui-swatch`, `ui-tabs`, `ui-tile`…), chargé par `BuilderShell.svelte` après `ui-tokens.css`. Un composant du panneau (`panels/`, `traits/`) n'écrit ni couleur, ni px, ni rem, ni `--yack-*` : il lit `--ui-*` (contrôlé par `test/builder-tokens.test.ts`). Le magenta ne remplit jamais un bouton de réglage : il souligne l'onglet actif, borde le segment actif et colore le focus.
- **Pictogrammes** : `@lucide/svelte` par `#lib/editor/icons.ts`, liste fermée (`test/editor-icons.test.ts`) : une icône nouvelle s'ajoute dans ce fichier et dans le test. Infobulles en CSS (`data-tip`) : au survol après 400 ms, immédiates au focus clavier.
- **Onglet Style** : trois groupes, Couleurs, Titre, Mise en page (`#lib/editor/panels/panelLayout.ts`, ordre de la maquette). Il ne montre que les réglages qui changent le bloc (`styleTraits(type)`, `styleHasEffect()` de `registry.ts`, contrôlés contre les composants et le SCSS par `test/style-fields.test.ts`) : un bloc qui rend un titre nouveau, ou un élément coloré par l'accent, s'y ajoute. Un réglage sans effet reste dans l'éditeur (`editorStyleTraits()`, `onlyWhenSet`) : le panneau le montre si le bloc porte déjà une valeur, pour qu'elle reste visible et effaçable. « Revenir au réglage par défaut » est le bouton à droite de l'étiquette ; quand rien n'est posé, aucun choix n'est allumé et l'étiquette dit « (par défaut) » aux lecteurs d'écran. « Enregistrer comme section » est l'icône de l'en-tête du panneau. Les libellés des champs s'affichent tels que le registre les écrit (`traitLabel`, `#lib/editor/traits/registerTraits.ts`).
- **Champs à pictogrammes** (`yack-icons`, `#lib/editor/traits/YackIcons.svelte`) : choix unique en `radiogroup`, chaque option avec son nom (`aria-label`), son infobulle et son icône (`icon` de l'option dans `registry.ts`, type `ChoiceIcon`) ; les flèches déplacent le focus, Entrée ou Espace choisit. Largeur, Alignement, Alignement vertical, Arrondi, Position du visuel.
- **Espacement** (`yack-spacing`, `#lib/editor/traits/YackSpacing.svelte`, logique dans `#lib/editor/traits/spacing.ts`) : un champ pour les quatre propriétés `hautEspace`, `droiteEspace`, `basEspace`, `gaucheEspace`, chacune toujours enregistrée dans son attribut `yb-<propriété>` ; côtés liés à l'ouverture quand ils sont égaux, menu « Pas » des pas de la charte (et « Par défaut »), cases en lecture seule au niveau Charte.

### Bloc sélectionné

Règles pures dans `#lib/editor/selection.ts` (testées par `test/selection.test.ts`), branchées dans `grapesSetup.ts` (`installSelectionGestures`).

- Un clic sur un bloc ouvre l'onglet Contenu ; un nouveau clic sur le bloc déjà sélectionné bascule entre Contenu et Style. Les clics suivants d'un double clic ne comptent pas ; sur un texte, la bascule attend 300 ms pour laisser place au double clic qui ouvre la saisie.
- Échap désélectionne (pendant la saisie d'un texte, il termine d'abord la saisie). Suppr ou Retour arrière demandent confirmation dans une boîte du builder avant de supprimer le bloc ; Ctrl+Z le rétablit. La corbeille de la barre d'outils du canevas passe par la même confirmation. Rien de cela n'agit dans un champ, dans un texte en saisie, avec une touche de modification, ni tant qu'une fenêtre modale du builder est ouverte (confirmation, médiathèque) ; un bloc obligatoire de template ne se supprime pas. Le raccourci de suppression de GrapesJS (`core:component-delete`) est remplacé.
- Le bloc sélectionné porte un contour gris de 1 px et, dans ses coins intérieurs, ses quatre espacements (haut, droite, bas, gauche ; « défaut » pour un côté non posé), masqués quand aucun n'est posé. Ils sont dessinés dans les outils de GrapesJS, hors du zoom du canevas, avec les variables de `ui-tokens.css` (`--ui-text-2xs`).
