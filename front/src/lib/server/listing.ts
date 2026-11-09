// Header search listing (/recherche, #lib/remote/catalog.remote.ts). Category pages (/produits/…,
// /pieces/…) are rendered through their CMS template (#lib/server/templates.ts).
import type { Breadcrumb, ListingParams, ListingSearch, ProductPage, SortOption } from '#lib/types.js';
import { listingParams, SECTIONS } from './catalog.js';
import { SEARCH_SORTS, searchPage } from './search.js';
import { sylius, type ShopEvent } from './sylius.js';

export interface ListingData {
	heading: string;
	breadcrumb: Breadcrumb[];
	menu: { items: { name: string; url: string }[]; up: string | null } | null;
	result: ProductPage;
	params: ListingParams;
	sorts: SortOption[];
}

/** Header search (/recherche?q=): the whole catalog by relevance (reference, name, brand, range, category). */
export async function searchListing(event: ShopEvent, search: ListingSearch): Promise<ListingData> {
	const { viewer } = event.locals;
	const params: ListingParams = { ...listingParams(search, viewer), sort: SEARCH_SORTS[0].key };
	const empty: ProductPage = { items: [], total: 0, page: 1, pages: 1 };

	return {
		heading: params.q ? `Résultats pour « ${params.q} »` : 'Rechercher un produit',
		breadcrumb: [
			{ label: 'Accueil', url: '/' },
			{ label: 'Recherche', url: null }
		],
		menu: { items: SECTIONS.map((s) => ({ name: s.label, url: s.url })), up: null },
		result: params.q ? await searchPage(sylius(event), viewer, params) : empty,
		params,
		sorts: SEARCH_SORTS
	};
}
