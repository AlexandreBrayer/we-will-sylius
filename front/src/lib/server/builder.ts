// Builder (/cms-admin) data: Sylius admin API with the builder JWT (locals.adminToken), canvas data
// read from the shop API as a visitor (no prices). Used by #lib/remote/builder.remote.ts, whose
// functions are served under /_app/remote/… — outside the /cms-admin guard of hooks.server.ts:
// every call goes through `asAdmin`.
import { error, redirect } from '@sveltejs/kit';
import type { Catalog, CmsTemplate, PageBlock, ProductContext, TaxonContext, TaxonListing, TemplateType } from '#lib/blocks/types.js';
import { VISITOR, type SortKey, type TaxonNode } from '#lib/types.js';
import { adminLoginUrl } from './adminAuth.js';
import { blockCatalog, catalogTree, latestProducts, listProducts } from './catalog.js';
import { pageUrl } from './session.js';
import { AdminAuthError, adminTemplates, ApiError, shopClient, type ShopEvent } from './sylius.js';
import { normalizeBlocks, productSample, taxonListing, taxonSample } from './templates.js';

/**
 * Calls the admin API with the builder JWT. Expired session: back to the login form (queries,
 * forms), or a 401 the page turns into that redirection (`command`: commands cannot redirect).
 * `command`: Sylius refusals (422 « le template par défaut doit être publié »…) become HTTP errors
 * whose message the editor shows. `notFound`: message of the 404 raised on an unknown resource.
 */
export async function asAdmin<T>(
	event: ShopEvent,
	call: (token: string | null) => Promise<T>,
	{ command = false, notFound }: { command?: boolean; notFound?: string } = {}
): Promise<T> {
	try {
		return await call(event.locals.adminToken);
	} catch (e) {
		if (e instanceof AdminAuthError) {
			if (!command) redirect(303, adminLoginUrl(pageUrl(event)));
			error(401, 'Session expirée : reconnectez-vous.');
		}
		if (notFound && e instanceof ApiError && e.status === 404) error(404, notFound);
		if (command && e instanceof ApiError && e.status < 500) error(e.status, e.message);
		throw e;
	}
}

/** Template as the editor reads it (block without props: `{}`, not the `[]` of PHP). */
export function editableTemplate(template: CmsTemplate): CmsTemplate {
	return { ...template, blocks: normalizeBlocks(template.blocks) };
}

/** Builder session check of the calls that only read public data. */
export function requireAdmin(event: ShopEvent): void {
	if (!event.locals.adminToken) redirect(303, adminLoginUrl(pageUrl(event)));
}

/** Blocks of a new template: the standard layout of its type (mandatory block included). */
export const NEW_TEMPLATE_BLOCKS: Record<TemplateType, PageBlock[]> = {
	product: [
		{ type: 'produit-galerie', props: {} },
		{ type: 'produit-infos', props: {} },
		{ type: 'produit-achat', props: {} },
		{ type: 'produit-description', props: { title: '' } },
		{ type: 'produit-caracteristiques', props: { title: '' } }
	],
	taxon: [
		{ type: 'categorie-entete', props: { description: 'oui' } },
		{ type: 'categorie-produits', props: { perPage: '9', sort: 'position' } }
	]
};

const visitorApi = () => shopClient(null);

/** Sample of the template preview: a product / category of the catalog, rendered as a visitor sees it. */
export interface TemplateSample {
	code: string;
	/** Shop page of the sample ("Voir" link, ?apercu=<template id>). */
	url: string;
	product: ProductContext | null;
	taxon: TaxonContext | null;
}

/** Catalog categories (Clik&Yack, Clik&Yack Parts) whose path ("Pompes à chaleur › Air/eau") or code contains `q`. */
export async function catalogTaxons(event: ShopEvent, q: string, limit = 15): Promise<{ code: string; name: string; path: string }[]> {
	requireAdmin(event);
	const needle = q.toLocaleLowerCase('fr');
	const found: { code: string; name: string; path: string }[] = [];
	const walk = (nodes: TaxonNode[], parents: string[]) => {
		for (const node of nodes) {
			const trail = [...parents, node.name];
			const path = trail.join(' › ');
			if (path.toLocaleLowerCase('fr').includes(needle) || node.code.includes(needle)) found.push({ code: node.code, name: node.name, path });
			walk(node.children, trail);
		}
	};
	walk(await catalogTree(), []);
	return found.slice(0, limit);
}

/**
 * Product / category shown in the editor of template `id`: `code` when given, otherwise the first
 * assigned product / category (a product of the first assigned category), else a catalog default.
 * null: unknown code.
 */
export async function templateSample(event: ShopEvent, id: number, type: TemplateType, code: string | null): Promise<TemplateSample | null> {
	const api = visitorApi();
	if (code === null) {
		const assigned = await asAdmin(event, (token) => adminTemplates.assignments(token, id), { notFound: 'Template introuvable' });
		if (type === 'taxon') {
			code = assigned.taxonCodes[0] ?? (await catalogTree())[0]?.children[0]?.code ?? null;
		} else {
			code = assigned.productCodes[0] ?? null;
			const taxonCode = assigned.taxonCodes[0];
			if (code === null && taxonCode) {
				const page = await listProducts(api, VISITOR, { taxonCode, q: '', sort: 'position', page: 1, perPage: 1 });
				code = page.items[0]?.code ?? null;
			}
			code ??= (await latestProducts(api, VISITOR, 1))[0]?.code ?? null;
		}
		if (code === null) return null;
	} else {
		requireAdmin(event);
	}

	if (type === 'product') {
		const product = await productSample(api, VISITOR, code);
		return product && { code, url: product.product.url, product, taxon: null };
	}
	const taxon = await taxonSample(api, VISITOR, code);
	return taxon && { code, url: taxon.taxon.url, product: null, taxon };
}

/** Products / taxons of the free blocks "produits" / "categories" in the canvas. */
export function canvasCatalog(event: ShopEvent, products: string[], taxons: string[]): Promise<Catalog> {
	requireAdmin(event);
	return blockCatalog(visitorApi(), VISITOR, products.slice(0, 24), taxons.slice(0, 24));
}

/** First page of a category for the "categorie-produits" props (canvas). */
export function canvasListing(event: ShopEvent, taxonCode: string, sort: SortKey, perPage: number): Promise<TaxonListing> {
	requireAdmin(event);
	return taxonListing(visitorApi(), VISITOR, taxonCode, {}, { sort, perPage });
}
