// Old Twig storefront URLs (/fr_FR/…, Sylius shop routes + Yack Twig routes) → front routes.
// Links already shared (e-mails, bookmarks, search engines, the admin's "show in shop" buttons)
// keep working through a 301; an unknown old URL ends in the regular 404.
import { catalogTree, taxonPath } from './catalog.js';

/** Sylius locale prefix of the Twig shop: /fr_FR, /en_US… (never a CMS slug: those are lowercase). */
const LOCALE_PREFIX = /^\/[a-z]{2}_[A-Z]{2}(?=\/|$)/;

type Rule = [pattern: RegExp, target: (match: RegExpMatchArray) => string, keepQuery?: boolean];

const RULES: Rule[] = [
	[/^$/, () => '/'],
	[/^products\/([^/]+)$/, (m) => `/produit/${m[1]}`],
	[/^(?:cart|checkout)(?:\/.*)?$/, () => '/panier'],
	[/^order\/.+$/, () => '/compte/commandes'],
	[/^login$/, () => '/connexion'],
	[/^(?:register|inscription)(?:\/.*)?$/, () => '/inscription'],
	[/^verify\/([^/]+)$/, (m) => `/activation/${m[1]}`],
	[/^forgotten-password$/, () => '/mot-de-passe/oublie'],
	[/^forgotten-password\/([^/]+)$/, (m) => `/mot-de-passe/reinitialiser/${m[1]}`],
	[/^account(?:\/dashboard)?$/, () => '/compte'],
	[/^account\/profile\/edit$/, () => '/compte/profil'],
	[/^account\/change-password$/, () => '/compte/profil#mot-de-passe'],
	[/^account\/address-book(?:\/.*)?$/, () => '/compte/adresses'],
	[/^account\/orders$/, () => '/compte/commandes'],
	[/^account\/orders\/([^/]+)$/, (m) => `/compte/commandes/${m[1]}`],
	[/^compte\/acces-commande$/, () => '/compte/acces-commande'],
	// ?serial= is redirected again by /sav to /sav/{serial}
	[/^sav$/, () => '/sav', true],
	// The front accepts a ServiceCode in place of a serial number
	[/^sav\/modele\/([^/]+)(?:\/.*)?$/, (m) => `/sav/${m[1]}`],
	// Same filters (?secteur=&gamme=&activite=)
	[/^trouver-un-installateur$/, () => '/trouver-un-installateur', true]
];

/** Front URL replacing an old Twig shop URL, null when `url` is not one (or has no equivalent). */
export async function legacyShopUrl(url: URL): Promise<string | null> {
	const prefix = url.pathname.match(LOCALE_PREFIX);
	if (!prefix) return null;

	// Path kept percent-encoded: captured slugs / tokens are reused as is.
	const rest = url.pathname.slice(prefix[0].length).replace(/^\/+|\/+$/g, '');

	const taxon = rest.match(/^taxons\/(.+)$/);
	if (taxon) {
		const trail = taxonPath(await catalogTree(), (node) => node.slug === taxon[1]);
		return trail.at(-1)?.url ?? null;
	}

	for (const [pattern, target, keepQuery] of RULES) {
		const match = rest.match(pattern);
		if (match) return target(match) + (keepQuery ? url.search : '');
	}
	return null;
}
