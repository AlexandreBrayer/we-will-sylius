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
make yack-front                                  # docker compose up -d front nginx (vite dev, HMR)
FRONT_MODE=prod make yack-front                  # npm run build && node build
cd front && npm install && npm run check         # svelte-check (0 erreur attendu)
```

Variable : `SYLIUS_API_URL` (Sylius vu du serveur SvelteKit, `http://nginx` en Docker), déclarée dans
`src/env.ts` et lue via `$app/env/private`. `ORIGIN` est fournie par compose pour `node build`.

## BFF

Le navigateur ne parle qu'au front : aucun JWT côté client.

| Cookie (httpOnly, SameSite=Lax) | Contenu |
|---|---|
| `yack_session` | JWT shop du client (`POST /api/v2/shop/customers/token`). Durée = expiration du JWT si « Rester connecté », sinon cookie de session. |
| `yack_cart` | `tokenValue` du panier ECOM (30 jours). Repris à la connexion (`POST /shop/orders` rend le dernier panier non vide du client) ; ignoré et effacé s'il ne pointe plus sur un panier (`state` ≠ `cart`). |
| `yack_cms_token` | JWT admin du builder (`/cms-admin`). |

- `src/hooks.server.ts` lit les cookies et charge `GET /api/v2/shop/yack/me` dans `locals.viewer`
  (visiteur = constante `VISITOR`, sans appel). Un 401 ferme la session.
- `#lib/server/sylius.ts` : client typé. `sylius(event)` transmet le JWT ; sur 401 il ferme la session et
  redirige vers `/connexion?redirect=…&expire=1`. `ApiError` porte `status`, `message` et `violations`
  (422 API Platform).
- Le layout racine (`+layout.server.ts`) expose `viewer` et le « chrome » (panier, pages du pied de page,
  publication de `nos-services`) à toutes les pages boutique.
- Les mutations passent par des form actions (`+page.server.ts`) enrichies par `use:enhance` ; elles
  fonctionnent sans JavaScript. Exemple : `?/addToCart` sur la fiche produit.

```ts
export const actions: Actions = {
	default: async (event) => {
		try {
			await sylius(event).post('/…', body);
		} catch (e) {
			if (e instanceof ApiError && e.status < 500) return fail(e.status, { error: e.message });
			throw e;
		}
	}
};
```

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
| `/connexion`, `/deconnexion` (POST) | session client |
| `/cms-admin/login`, `/cms-admin/builder/[slug]` | builder GrapesJS (lien depuis l'admin Sylius) |

Les URLs des e-mails (`/activation/…`, `/mot-de-passe/…`, `/compte/…`, `/panier`, `/commande`…) sont
portées par les autres écrans du front. Slugs CMS réservés : `CmsPage::RESERVED_SLUGS` côté Sylius.

Non portés depuis le Twig : avis/notes produits, liste de souhaits, sélecteur de langue (FR seul).
