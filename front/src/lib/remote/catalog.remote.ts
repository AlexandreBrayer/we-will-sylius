// Catalog reads: products, listings (/produits/…, /pieces/…, /recherche). Prices: the API hides
// them from visitors and PRO accounts (#lib/server/catalog.ts maps with the viewer).
import { error } from '@sveltejs/kit';
import { getRequestEvent, query } from '$app/server';
import * as v from 'valibot';
import { breadcrumb, catalogTree, latestProducts, productBySlug, taxonPath } from '#lib/server/catalog.js';
import { searchListing, sectionListing, type ListingData } from '#lib/server/listing.js';
import { sylius } from '#lib/server/sylius.js';
import type { Breadcrumb, ProductDetail, ProductSummary } from '#lib/types.js';

/** Raw URL query of a listing (`listingSearch(page.url)`, #lib/types.ts); normalized server-side. */
const ListingSearch = v.object({
	q: v.optional(v.string()),
	tri: v.optional(v.string()),
	par: v.optional(v.string()),
	page: v.optional(v.string())
});

/** "Derniers produits" (homepage, product page): the 8 most recent products. */
export const getLatestProducts = query((): Promise<ProductSummary[]> => {
	const event = getRequestEvent();
	return latestProducts(sylius(event), event.locals.viewer);
});

/** Product page (/produit/[slug]): product and breadcrumb (main taxon); 404 when unknown / disabled. */
export const getProduct = query(v.string(), async (slug): Promise<{ product: ProductDetail; breadcrumb: Breadcrumb[] }> => {
	const event = getRequestEvent();
	const [product, roots] = await Promise.all([productBySlug(sylius(event), event.locals.viewer, slug), catalogTree()]);
	if (!product) error(404, 'Produit introuvable');

	const trail = product.mainTaxonCode ? taxonPath(roots, (node) => node.code === product.mainTaxonCode) : [];
	return { product, breadcrumb: breadcrumb(trail, product.name) };
});

/** Taxon listing: `section` root URL, `path` = rest of the URL (taxon slugs); 404 when unknown. */
export const getSectionListing = query(
	v.object({ section: v.picklist(['/produits', '/pieces']), path: v.string(), search: ListingSearch }),
	({ section, path, search }): Promise<ListingData> => sectionListing(getRequestEvent(), section, path, search)
);

/** Header search (/recherche?q=): name search across the catalog, exact product code first. */
export const getSearchListing = query(ListingSearch, (search): Promise<ListingData> => searchListing(getRequestEvent(), search));
