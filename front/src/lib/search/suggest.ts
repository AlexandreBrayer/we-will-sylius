// Header search suggestions (#lib/layout/SearchSuggest.svelte): answer of the search API mapped to the
// front, options of the list in keyboard order, debounce. No Svelte nor SvelteKit here: `npm test`.

/** Suggestions start from this number of characters (same rule as the API, SearchCatalog). */
export const SUGGEST_MIN_CHARS = 2;

/** Delay after the last keystroke before asking for suggestions. */
export const SUGGEST_DELAY_MS = 200;

export interface ProductSuggestion {
	code: string;
	name: string;
	brand: string | null;
	/** Thumbnail path on the shop origin, null without image. */
	image: string | null;
	url: string;
}

export interface CategorySuggestion {
	code: string;
	name: string;
	url: string;
}

export interface SearchSuggestions {
	/** Text the suggestions answer (normalized by the API). */
	query: string;
	products: ProductSuggestion[];
	categories: CategorySuggestion[];
}

/** Answer of GET /api/v2/shop/yack/search/suggest (docs/API_FRONT.md). */
export interface ApiSuggestions {
	query: string;
	engine: string;
	products: { code: string; name: string; slug: string; brand: string | null; image: string | null }[];
	categories: { code: string; name: string; slug: string }[];
}

export const noSuggestions = (query = ''): SearchSuggestions => ({ query, products: [], categories: [] });

export const shouldSuggest = (text: string): boolean => text.trim().length >= SUGGEST_MIN_CHARS;

/** Results page of a search (form GET of the header). */
export const searchHref = (text: string): string => `/recherche?${new URLSearchParams({ q: text.trim() })}`;

/** Media URLs carry the host Sylius was called with: keep the path, served on the shop origin. */
export const mediaPath = (url: string): string => (/^https?:\/\//.test(url) ? new URL(url).pathname : url);

/**
 * Suggestions of the front: product pages, category pages resolved by `categoryUrl` (a category
 * outside the shop menu is left out).
 */
export function toSuggestions(api: ApiSuggestions, categoryUrl: (code: string) => string | null): SearchSuggestions {
	return {
		query: api.query,
		products: api.products.map((p) => ({
			code: p.code,
			name: p.name,
			brand: p.brand,
			image: p.image ? mediaPath(p.image) : null,
			url: `/produit/${p.slug}`
		})),
		categories: api.categories.flatMap((c) => {
			const url = categoryUrl(c.code);
			return url ? [{ code: c.code, name: c.name, url }] : [];
		})
	};
}

/** Links of the list in display and keyboard order: products, categories, then all the results. */
export function optionUrls(suggestions: SearchSuggestions): string[] {
	return [...suggestions.products.map((p) => p.url), ...suggestions.categories.map((c) => c.url), searchHref(suggestions.query)];
}

/**
 * Active option after an arrow key (-1: none, the focus stays in the field), wrapping around;
 * null when the key does not move the selection.
 */
export function moveActive(active: number, count: number, key: string): number | null {
	if (key !== 'ArrowDown' && key !== 'ArrowUp') return null;
	if (count === 0) return -1;
	if (key === 'ArrowDown') return active >= count - 1 ? 0 : active + 1;
	return active <= 0 ? count - 1 : active - 1;
}

export interface Timers {
	set: (callback: () => void, delay: number) => unknown;
	clear: (handle: unknown) => void;
}

const realTimers: Timers = {
	set: (callback, delay) => setTimeout(callback, delay),
	clear: (handle) => clearTimeout(handle as ReturnType<typeof setTimeout>)
};

/** `fn` runs `delay` ms after the last call, with its arguments; `cancel` drops the pending run. */
export function debounce<A extends unknown[]>(fn: (...args: A) => void, delay: number, timers: Timers = realTimers) {
	let pending: unknown = null;
	return {
		call(...args: A) {
			if (pending !== null) timers.clear(pending);
			pending = timers.set(() => {
				pending = null;
				fn(...args);
			}, delay);
		},
		cancel() {
			if (pending !== null) timers.clear(pending);
			pending = null;
		}
	};
}
