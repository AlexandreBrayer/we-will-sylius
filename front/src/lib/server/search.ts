// Shop search through the search API of Sylius (GET /api/v2/shop/yack/search, docs/API_FRONT.md §4):
// Meilisearch or the database answers, the API picks. Results page: codes by relevance, then the
// product cards from the catalogue API, so prices stay hidden or shown according to the profile.
import { isRedirect } from '@sveltejs/kit';
import { noSuggestions, shouldSuggest, toSuggestions, type ApiSuggestions, type SearchSuggestions } from '#lib/search/suggest.js';
import type { ListingParams, ProductPage, SortOption, Viewer } from '#lib/types.js';
import { catalogTree, productsByCode, searchProducts, taxonPath } from './catalog.js';
import { shopClient, type ShopClient } from './sylius.js';

/** Answer of GET /api/v2/shop/yack/search. */
interface ApiSearch {
	query: string;
	engine: string;
	total: number;
	page: number;
	limit: number;
	codes: string[];
}

/** The results come by relevance: the search page offers no other order. */
export const SEARCH_SORTS: SortOption[] = [{ key: 'position', label: 'Pertinence' }];

/** One page of results, most relevant first. */
export async function relevantProducts(api: ShopClient, viewer: Viewer, { q, page, perPage }: ListingParams): Promise<ProductPage> {
	const found = await api.get<ApiSearch>('/yack/search', { q, limit: perPage, page });

	return {
		items: await productsByCode(api, viewer, found.codes),
		total: found.total,
		page,
		pages: Math.max(1, Math.ceil(found.total / perPage))
	};
}

/**
 * Results page that never fails because of the search: when the search API errs, the name
 * search of the catalogue API answers (an expired session still leads to the login page).
 */
export async function searchPage(api: ShopClient, viewer: Viewer, params: ListingParams): Promise<ProductPage> {
	try {
		return await relevantProducts(api, viewer, params);
	} catch (error) {
		if (isRedirect(error)) throw error;
		console.warn('Recherche : API de recherche indisponible, recherche par nom du catalogue.', error);
		return searchProducts(api, viewer, params);
	}
}

/** Suggestions of the header (public data: no session needed, an expired one never redirects). */
export async function searchSuggestions(text: string): Promise<SearchSuggestions> {
	if (!shouldSuggest(text)) return noSuggestions(text);

	const [answer, roots] = await Promise.all([shopClient(null).get<ApiSuggestions>('/yack/search/suggest', { q: text }), catalogTree()]);
	return toSuggestions(answer, (code) => taxonPath(roots, (node) => node.code === code).at(-1)?.url ?? null);
}
