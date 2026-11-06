// Loads of the product listings: catalog sections (/produits/…, /pieces/…) and search.
import { error, type RequestEvent } from '@sveltejs/kit';
import type { Breadcrumb, ListingParams, ProductPage, SortOption } from '#lib/types.js';
import {
	breadcrumb,
	catalogTree,
	listingParams,
	listProducts,
	searchProducts,
	SECTIONS,
	sortOptions,
	taxonPath
} from './catalog.js';
import { sylius } from './sylius.js';

export interface ListingData {
	heading: string;
	description: string | null;
	breadcrumb: Breadcrumb[];
	menu: { items: { name: string; url: string }[]; up: string | null } | null;
	result: ProductPage;
	params: ListingParams;
	sorts: SortOption[];
}

type SectionUrl = (typeof SECTIONS)[number]['url'];

/** Taxon page: `path` = rest of the URL after the section (taxon slugs below the section root). */
export async function sectionListing(event: RequestEvent, section: SectionUrl, path: string): Promise<ListingData> {
	const url = path ? `${section}/${path.replace(/\/+$/, '')}` : section;
	const trail = taxonPath(await catalogTree(), (node) => node.url === url);
	const taxon = trail.at(-1);
	if (!taxon) error(404, 'Catégorie introuvable');

	const { viewer } = event.locals;
	const params = listingParams(event.url, viewer);

	return {
		heading: taxon.name,
		description: taxon.description,
		breadcrumb: breadcrumb(trail),
		menu: {
			items: taxon.children.map((child) => ({ name: child.name, url: child.url })),
			up: trail.at(-2)?.url ?? null
		},
		result: await listProducts(sylius(event), viewer, { ...params, taxonCode: taxon.code }),
		params,
		sorts: sortOptions(viewer)
	};
}

/** Header search (/recherche?q=): name search across the whole catalog, product code first. */
export async function searchListing(event: RequestEvent): Promise<ListingData> {
	const { viewer } = event.locals;
	const params = listingParams(event.url, viewer);
	const empty: ProductPage = { items: [], total: 0, page: 1, pages: 1 };

	return {
		heading: params.q ? `Résultats pour « ${params.q} »` : 'Rechercher un produit',
		description: null,
		breadcrumb: [
			{ label: 'Accueil', url: '/' },
			{ label: 'Recherche', url: null }
		],
		menu: { items: SECTIONS.map((s) => ({ name: s.label, url: s.url })), up: null },
		result: params.q ? await searchProducts(sylius(event), viewer, params) : empty,
		params,
		sorts: sortOptions(viewer)
	};
}
