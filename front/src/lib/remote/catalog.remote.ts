// Catalog reads: product pages and category pages (rendered through their CMS template, #lib/server/templates.ts),
// search listing. Prices: the API hides them from visitors and PRO accounts (#lib/server/catalog.ts maps with the viewer).
import { getRequestEvent, query } from '$app/server';
import * as v from 'valibot';
import type { ProductContext, TaxonContext } from '#lib/blocks/types.js';
import { latestProducts } from '#lib/server/catalog.js';
import { searchListing, type ListingData } from '#lib/server/listing.js';
import { searchSuggestions } from '#lib/server/search.js';
import type { SearchSuggestions } from '#lib/search/suggest.js';
import { sylius } from '#lib/server/sylius.js';
import { productPage, taxonPage, type TemplatePage } from '#lib/server/templates.js';
import type { ProductSummary } from '#lib/types.js';

/** Raw URL query of a listing (`listingSearch(page.url)`, #lib/types.ts); normalized server-side. */
const ListingSearch = v.object({
	q: v.optional(v.string()),
	tri: v.optional(v.string()),
	page: v.optional(v.string()),
	marque: v.optional(v.string()),
	gamme: v.optional(v.string()),
	puissance: v.optional(v.string()),
	fluide: v.optional(v.string())
});

/** ?apercu=<template id>: template previewed by an administrator logged into the builder (ignored otherwise). */
const Preview = v.optional(v.nullable(v.pipe(v.number(), v.integer(), v.minValue(1))), null);

/** "Derniers produits" (homepage): the 8 most recent products. */
export const getLatestProducts = query((): Promise<ProductSummary[]> => {
	const event = getRequestEvent();
	return latestProducts(sylius(event), event.locals.viewer);
});

/** Product page (/produit/[slug]): product, breadcrumb and blocks of its template; 404 when unknown / disabled. */
export const getProductPage = query(
	v.object({ slug: v.string(), preview: Preview }),
	({ slug, preview }): Promise<TemplatePage<ProductContext>> => productPage(getRequestEvent(), slug, preview)
);

/** Category page: `section` root URL, `path` = rest of the URL (taxon slugs); blocks of its template; 404 when unknown. */
export const getTaxonPage = query(
	v.object({ section: v.picklist(['/produits', '/pieces']), path: v.string(), search: ListingSearch, preview: Preview }),
	({ section, path, search, preview }): Promise<TemplatePage<TaxonContext>> => taxonPage(getRequestEvent(), section, path, search, preview)
);

/** Header search (/recherche?q=): the whole catalog by relevance. */
export const getSearchListing = query(ListingSearch, (search): Promise<ListingData> => searchListing(getRequestEvent(), search));

/** Suggestions of the header search box: 6 products and 3 categories from 2 characters (text cut at 100 characters). */
export const getSearchSuggestions = query(
	v.pipe(
		v.string(),
		v.trim(),
		v.transform((text) => text.slice(0, 100))
	),
	(text): Promise<SearchSuggestions> => searchSuggestions(text)
);
