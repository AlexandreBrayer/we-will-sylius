// Link field of the block panel (D9, YackLink.svelte): readable label of a stored link, site
// shortcuts, check of a typed address. Pure (no SvelteKit import): the remote functions of
// builder.remote.ts build the label with it, test/link.test.ts checks it against the fixtures.
// Relative `.ts` imports: run by node in the tests.
//
// The stored value does not change format (CmsExtension::link(), resolveLink() of the shop):
//   « page:<slug> »   CMS page (picked in the search);
//   « /… »            path of the front: shortcut (/sav), category (/produits/…), product (/produit/<slug>),
//                     CMS page written as a path (/nos-documentations), anything else of the site;
//   http(s)://…, mailto:…, tel:…  as is.
// A value is never rewritten when it is read: the field writes only what is picked or typed.
import { resolveLink } from '../../blocks/parse.ts';
import type { TaxonNode } from '../../types.ts';

/** Shortcuts of the site: the ticket's six, then the other entry points the fixtures link to. */
export const SHORTCUTS: readonly { path: string; label: string }[] = [
	{ path: '/', label: 'Accueil' },
	{ path: '/sav', label: 'SAV et pièces détachées' },
	{ path: '/assistance', label: 'Manuels et notices' },
	{ path: '/trouver-un-installateur', label: 'Trouver un installateur' },
	{ path: '/contact', label: 'Contact' },
	{ path: '/panier', label: 'Panier' },
	{ path: '/produits', label: 'Catalogue Clik&Yack' },
	{ path: '/pieces', label: 'Catalogue Clik&Yack Parts' },
	{ path: '/connexion', label: 'Connexion' },
	{ path: '/inscription', label: 'Créer un compte PRO' },
	{ path: '/compte', label: 'Mon compte' },
	{ path: '/compte/commandes', label: 'Mes commandes' }
];

/** Kind of a link, for the label and the search groups. */
export type LinkKind = 'empty' | 'page' | 'shortcut' | 'category' | 'product' | 'path' | 'web' | 'mail' | 'phone' | 'invalid';

/** Names known by the server (builder.remote.ts getLinkLabel); null: not loaded. */
export interface LinkNames {
	/** CMS page slug → title. */
	pages: ReadonlyMap<string, string>;
	/** Category URL (/produits/…) → path « Clik&Yack › Pompe à chaleur… ». */
	categories: ReadonlyMap<string, string>;
	/** Product slug → name. */
	products: ReadonlyMap<string, string>;
}

export interface LinkDescription {
	kind: LinkKind;
	/** Readable label: « Page : Nos services », « Site : SAV et pièces détachées »… */
	label: string;
	/** The target is unknown (page or product deleted, typo): the label says so. */
	missing: boolean;
}

/** Option of the search list; `value` is what the field stores. */
export interface LinkOption {
	value: string;
	label: string;
	kind: 'shortcut' | 'page' | 'category' | 'product' | 'address';
	/** Second line: address, code, « brouillon »… */
	detail?: string;
}

const SLUG = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;

/** Lower case without accents, for the search. */
export function foldText(value: string): string {
	return value.normalize('NFD').replace(/\p{M}/gu, '').toLocaleLowerCase('fr').trim();
}

/** Path of a site link and what follows it (« ?marque=Yack », « #faq »). */
function splitPath(link: string): { path: string; rest: string } {
	const cut = link.search(/[?#]/);
	return cut === -1 ? { path: link, rest: '' } : { path: link.slice(0, cut), rest: link.slice(cut) };
}

/** Lookups a value needs (builder.remote.ts loads only those). */
export function linkNeeds(value: string): { page?: string; category?: string; product?: string } {
	const link = value.trim();
	if (link.startsWith('page:')) return { page: link.slice(5) };
	if (!link.startsWith('/') || resolveLink(link) === '') return {};
	const { path } = splitPath(link);
	if (SHORTCUTS.some((shortcut) => shortcut.path === path)) return {};
	const product = /^\/produit\/([^/]+)$/.exec(path);
	if (product) return { product: decodeURIComponent(product[1]) };
	if (/^\/(produits|pieces)\//.test(path)) return { category: path };
	const page = /^\/([a-z0-9-]+)$/.exec(path);
	return page ? { page: page[1] } : {};
}

/**
 * Readable label of a stored link. `names` null (not loaded yet, or the lookup failed): pages,
 * categories and products show their code, without claiming they are missing.
 */
export function describeLink(value: string, names: LinkNames | null): LinkDescription {
	const link = value.trim();
	const found = (kind: LinkKind, label: string): LinkDescription => ({ kind, label, missing: false });
	const lost = (kind: LinkKind, label: string): LinkDescription => ({ kind, label, missing: true });
	if (link === '') return found('empty', 'Aucun lien');
	if (resolveLink(link) === '') return lost('invalid', `Lien refusé par la boutique : ${link}`);

	if (link.startsWith('page:')) {
		const slug = link.slice(5);
		const title = names?.pages.get(slug);
		if (title !== undefined) return found('page', `Page : ${title}`);
		return names ? lost('page', `Page introuvable : ${slug}`) : found('page', `Page : ${slug}`);
	}
	if (/^mailto:/i.test(link)) return found('mail', `E-mail : ${link.slice(7)}`);
	if (/^tel:/i.test(link)) return found('phone', `Téléphone : ${link.slice(4)}`);
	if (!link.startsWith('/')) return found('web', `Lien externe : ${link}`);

	const { path, rest } = splitPath(link);
	const suffix = rest === '' ? '' : ` (${rest})`;
	const shortcut = SHORTCUTS.find((entry) => entry.path === path);
	if (shortcut) return found('shortcut', `Site : ${shortcut.label}${suffix}`);
	const needs = linkNeeds(link);
	if (needs.product !== undefined) {
		const name = names?.products.get(needs.product);
		if (name !== undefined) return found('product', `Produit : ${name}${suffix}`);
		return names ? lost('product', `Produit introuvable : ${needs.product}`) : found('product', `Produit : ${needs.product}${suffix}`);
	}
	if (needs.category !== undefined) {
		const trail = names?.categories.get(needs.category);
		if (trail !== undefined) return found('category', `Catégorie : ${trail}${suffix}`);
		return names ? lost('category', `Catégorie introuvable : ${path}`) : found('category', `Catégorie : ${path}${suffix}`);
	}
	if (needs.page !== undefined) {
		const title = names?.pages.get(needs.page);
		if (title !== undefined) return found('page', `Page : ${title}${suffix}`);
	}
	return found('path', `Adresse du site : ${link}`);
}

/** Categories of the catalog tree: URL and path « Clik&Yack › … » (same path as searchTaxons). */
export function categoryEntries(nodes: readonly TaxonNode[], parents: string[] = []): { code: string; url: string; path: string }[] {
	return nodes.flatMap((node) => {
		const trail = [...parents, node.name];
		return [{ code: node.code, url: node.url, path: trail.join(' › ') }, ...categoryEntries(node.children, trail)];
	});
}

/** Shortcuts whose label or path contains `q` (all of them when `q` is empty). */
export function matchShortcuts(q: string): LinkOption[] {
	const needle = foldText(q);
	return SHORTCUTS.filter((entry) => needle === '' || foldText(entry.label).includes(needle) || entry.path.includes(needle)).map((entry) => ({
		value: entry.path,
		label: entry.label,
		kind: 'shortcut',
		detail: entry.path
	}));
}

/** CMS pages whose title or slug contains `q`, by title. */
export function matchPages(pages: readonly { slug: string; title: string; status?: string }[], q: string, limit = 8): LinkOption[] {
	const needle = foldText(q);
	return pages
		.filter((page) => SLUG.test(page.slug) && (needle === '' || foldText(page.title).includes(needle) || page.slug.includes(needle)))
		.sort((a, b) => a.title.localeCompare(b.title, 'fr'))
		.slice(0, limit)
		.map((page) => ({
			value: `page:${page.slug}`,
			label: page.title,
			kind: 'page',
			detail: page.status === 'draft' ? `/${page.slug}, brouillon : invisible tant qu'elle n'est pas publiée` : `/${page.slug}`
		}));
}

/** What the user typed is an address rather than a search (« / », « https: », « www. », « javascript: »…). */
export function looksLikeAddress(input: string): boolean {
	return /^(\/|www\.|[a-z][a-z0-9+.-]*:)/i.test(input.trim());
}

export type LinkCheck = { ok: true; value: string } | { ok: false; message: string };

export const MAX_LINK_LENGTH = 2000;

/**
 * Typed address: same rule as resolveLink() of the shop (http, https, mailto, tel, a path of the site
 * starting with a single « / », or page:<slug>); anything else (javascript:, data:, //host…) is refused.
 */
export function checkLinkInput(input: string): LinkCheck {
	const value = input.trim();
	if (value === '') return { ok: false, message: 'Saisissez une adresse.' };
	if (value.length > MAX_LINK_LENGTH) return { ok: false, message: 'Adresse trop longue (2 000 caractères au plus).' };
	if (/[\s\p{Cc}]/u.test(value)) return { ok: false, message: "Une adresse ne contient pas d'espace." };
	if (/^www\./i.test(value)) return { ok: false, message: `Ajoutez https:// devant l'adresse : https://${value}` };
	if (resolveLink(value) === '') {
		return { ok: false, message: 'Adresse refusée : un lien commence par https://, http://, mailto:, tel: ou par / pour une page du site.' };
	}
	if (/^https?:/i.test(value)) {
		let host = '';
		try {
			host = new URL(value).hostname;
		} catch {
			host = '';
		}
		// « https:///x » or « https://\x »: the URL parser finds a host the address does not show.
		if (host === '' || !/^https?:\/\/[^/\\]/i.test(value)) return { ok: false, message: 'Adresse web incomplète : https://nom-du-site.fr/…' };
	}
	if (/^(mailto|tel):$/i.test(value)) return { ok: false, message: 'Complétez l’adresse e-mail ou le numéro après les deux-points.' };
	return { ok: true, value };
}
