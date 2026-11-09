// Catalog of the shop (products, taxon tree, CMS pages) read from the Sylius shop API and mapped
// to the front types (#lib/types.ts). Prices come from the API, which hides them from visitors
// and PRO accounts; components still gate on viewer.canSeePrices.
import type { Catalog, CmsPage, CmsPageSummary, TaxonCard } from '#lib/blocks/types.js';
import {
	FILTER_PARAMS,
	type Breadcrumb,
	type Facet,
	type FacetCode,
	type ListingFilters,
	type ListingParams,
	type ListingSearch,
	type ProductAttribute,
	type ProductPage,
	type ProductDetail,
	type ProductSummary,
	type ProductType,
	type SortKey,
	type SortOption,
	type StockLevel,
	type TaxonNode,
	type Viewer
} from '#lib/types.js';
import { staleCache } from './staleCache.js';
import { ApiError, shopClient, type Query, type ShopClient } from './sylius.js';

// ── API shapes (docs/API_FRONT.md) ──

interface ApiProduct {
	code: string;
	name: string;
	slug: string;
	shortDescription: string | null;
	description: string | null;
	images: { type: string | null; path: string }[];
	defaultVariant: string | null;
	mainTaxon: string | null;
	/** IRIs of the product associations (/api/v2/shop/product-associations/{id}). */
	associations?: string[];
	defaultVariantData: { price: number | null; originalPrice: number | null; inStock: boolean } | null;
	yack: {
		type: ProductType | null;
		typeLabel: string | null;
		brand: { code: string | null; name: string } | null;
		range: string | null;
		/** False: product visible without price nor cart (Catalog `OnlineSale`). */
		soldOnline?: boolean;
		attributes: { code: string; name: string; type: string; value: unknown }[];
		stock: { tracked: boolean; available: number | null; inStock: boolean; label: string; level?: StockLevel };
	} | null;
}

interface ApiTaxon {
	code: string;
	name: string;
	slug: string;
	description: string | null;
	children: string[];
}

const LOCALE = 'fr_FR';
const iriCode = (iri: string): string => decodeURIComponent(iri.slice(iri.lastIndexOf('/') + 1));

/**
 * Media URLs carry the host Sylius was called with (http://nginx): keep the path, served by
 * nginx on the same origin, and swap the original for a LiipImagine thumbnail filter.
 */
function mediaUrl(path: string, filter: 'sylius_shop_product_thumbnail' | 'sylius_shop_product_large_thumbnail'): string {
	const pathname = path.startsWith('http') ? new URL(path).pathname : path;
	return pathname.replace('/sylius_original/', `/${filter}/`);
}

function attributeValue(value: unknown): string {
	if (typeof value === 'number') return value.toLocaleString('fr-FR');
	if (typeof value === 'boolean') return value ? 'Oui' : 'Non';
	if (Array.isArray(value)) return value.map(attributeValue).join(', ');
	return value === null || value === undefined ? '' : String(value);
}

function toSummary(product: ApiProduct, viewer: Viewer): ProductSummary {
	const image = product.images.find((i) => i.type === 'main') ?? product.images[0];
	const prices = viewer.canSeePrices ? product.defaultVariantData : null;

	return {
		code: product.code,
		name: product.name,
		slug: product.slug,
		url: `/produit/${product.slug}`,
		image: image ? mediaUrl(image.path, 'sylius_shop_product_thumbnail') : null,
		price: prices?.price ?? null,
		originalPrice: prices?.originalPrice ?? null,
		type: product.yack?.type ?? null,
		typeLabel: product.yack?.typeLabel ?? null,
		brand: product.yack?.brand?.name ?? null,
		inStock: product.yack?.stock.inStock ?? product.defaultVariantData?.inStock ?? null,
		stockLabel: product.yack?.stock.label ?? null,
		stock: product.yack?.stock.level
			? { level: product.yack.stock.level, label: product.yack.stock.label, available: product.yack.stock.available }
			: null,
		variantCode: product.defaultVariant ? iriCode(product.defaultVariant) : null,
		soldOnline: product.yack?.soldOnline ?? true
	};
}

/**
 * Attributes kept for the catalogue's own rules, never shown as characteristics: online sale (price and
 * cart), kind of article (badge of the card), names of the product's documents (Manuels et notices).
 */
const INTERNAL_ATTRIBUTES = ['vendu_en_ligne', 'type_article', 'documentation'];

/** Sylius image types of the product gallery; any other type (notice, fiche-technique…) is a document. */
const GALLERY_TYPES = [null, '', 'main', 'thumbnail'];

function toDetail(product: ApiProduct, viewer: Viewer): ProductDetail {
	const attributes: ProductAttribute[] = (product.yack?.attributes ?? [])
		.map((a) => ({ code: a.code, name: a.name, value: attributeValue(a.value) }))
		.filter((a) => a.value !== '' && !INTERNAL_ATTRIBUTES.includes(a.code));
	const gallery = product.images.filter((i) => GALLERY_TYPES.includes(i.type));
	const documents = product.images.filter((i) => !GALLERY_TYPES.includes(i.type));

	return {
		...toSummary(product, viewer),
		description: product.description,
		shortDescription: product.shortDescription,
		range: product.yack?.range ?? null,
		images: gallery.map((i) => ({
			thumbnail: mediaUrl(i.path, 'sylius_shop_product_thumbnail'),
			large: mediaUrl(i.path, 'sylius_shop_product_large_thumbnail')
		})),
		documents: documents.map((i) => ({
			label: (i.type ?? '').replace(/[-_]+/g, ' ').replace(/^./, (c) => c.toUpperCase()),
			url: i.path.startsWith('http') ? new URL(i.path).pathname : i.path
		})),
		attributes,
		mainTaxonCode: product.mainTaxon ? iriCode(product.mainTaxon) : null
	};
}

// ── Products ──

export const SORTS = {
	position: { label: 'Par position', query: {} },
	'nom-asc': { label: 'De A à Z', query: { 'order[translation.name]': 'asc', localeCode: LOCALE } },
	'nom-desc': { label: 'De Z à A', query: { 'order[translation.name]': 'desc', localeCode: LOCALE } },
	recent: { label: 'Plus récent en premier', query: { 'order[createdAt]': 'desc' } },
	ancien: { label: 'Plus ancien en premier', query: { 'order[createdAt]': 'asc' } },
	'prix-asc': { label: 'Le moins cher en premier', query: { 'order[price]': 'asc' }, prices: true },
	'prix-desc': { label: 'Le plus cher en premier', query: { 'order[price]': 'desc' }, prices: true }
} satisfies Record<SortKey, { label: string; query: Query; prices?: boolean }>;

/** Sort options offered to the viewer (price sorts only when prices are visible). */
export function sortOptions(viewer: Viewer): SortOption[] {
	return Object.entries(SORTS)
		.filter(([, sort]) => viewer.canSeePrices || !('prices' in sort))
		.map(([key, sort]) => ({ key: key as SortKey, label: sort.label }));
}
/** Page sizes offered by the "categorie-produits" block (`perPage` prop of the category template). */
export const PER_PAGE = [9, 18, 27] as const;

/** Initial sort / page size of a listing (the sort is dropped from the URLs when equal to it). */
export const LISTING_DEFAULTS: { sort: SortKey; perPage: number } = { sort: 'position', perPage: PER_PAGE[0] };

/**
 * Listing parameters from the URL query (?q=&tri=&page=, see `listingSearch`); absent or invalid
 * values → `defaults` (initial sort of a category template). The page size is always the one of
 * `defaults` (block of the category template, or the listing defaults): no URL parameter sets it.
 */
export function listingParams(search: ListingSearch, viewer: Viewer, defaults = LISTING_DEFAULTS): ListingParams {
	const page = Number(search.page);
	const validSort = sortOptions(viewer).find((option) => option.key === search.tri)?.key ?? defaults.sort;

	return {
		q: (search.q ?? '').trim(),
		sort: validSort,
		perPage: defaults.perPage,
		page: Number.isInteger(page) && page > 1 ? page : 1,
		filters: listingFilters(search)
	};
}

/**
 * Power bands of the "Puissance" facet (codes of the API, Catalog `CatalogFilter::POWER_BANDS`) as
 * the bounds of `yack_power_min` / `yack_power_max`. The API filter includes both bounds while a
 * band excludes its upper bound (but 9-12 includes 12): the excluded bound is approached by 0.01 kW
 * (powers of the Yack feed have one decimal), so the listing holds the products counted in the
 * band. To drop once the API takes the band itself (`yack_power_band`, proposed to PA).
 */
export const POWER_BANDS: Record<string, { min: number | null; max: number | null }> = {
	'0-3': { min: 0, max: 2.99 },
	'3-6': { min: 3, max: 5.99 },
	'6-9': { min: 6, max: 8.99 },
	'9-12': { min: 9, max: 12 },
	'12+': { min: 12.01, max: null }
};

/** Facet filters of the URL (?marque=&gamme=&puissance=&fluide=): trimmed, unknown power bands dropped. */
function listingFilters(search: ListingSearch): ListingFilters {
	const filters: ListingFilters = {};
	for (const [code, param] of Object.entries(FILTER_PARAMS) as [FacetCode, (typeof FILTER_PARAMS)[FacetCode]][]) {
		const value = (search[param] ?? '').trim().slice(0, 100);
		if (value === '' || (code === 'power' && !(value in POWER_BANDS))) continue;
		filters[code] = value;
	}
	return filters;
}

/** API query parameters of the facet filters (products and catalog-facets). */
function filterQuery(filters: ListingFilters = {}): Query {
	const band = filters.power ? POWER_BANDS[filters.power] : undefined;
	return {
		yack_brand: filters.brand,
		yack_range: filters.range,
		yack_refrigerant: filters.refrigerant,
		yack_power_min: band?.min ?? undefined,
		yack_power_max: band?.max ?? undefined
	};
}

/**
 * Facets of a section listing (GET /shop/yack/catalog-facets): values and counts of the products of
 * `type`, each facet counted with the filters of the other ones. Secondary content: [] when the API
 * fails or refuses the filters (the listing itself still answers).
 */
export async function listingFacets(api: ShopClient, type: ProductType, filters: ListingFilters): Promise<Facet[]> {
	try {
		const answer = await api.get<{ facets?: Facet[] }>('/yack/catalog-facets', { yack_type: type, ...filterQuery(filters) });
		return answer.facets ?? [];
	} catch (error) {
		if (error instanceof ApiError) return [];
		throw error;
	}
}

/** Products of a taxon (descendants included) and/or matching a name, sorted and paginated. */
export async function listProducts(
	api: ShopClient,
	viewer: Viewer,
	{ taxonCode, q, sort, page, perPage, filters }: Omit<ListingParams, 'filters'> & { filters?: ListingFilters; taxonCode?: string }
): Promise<ProductPage> {
	const { items, total } = await api.collection<ApiProduct>('/products', {
		taxon: taxonCode ? `/api/v2/shop/taxons/${taxonCode}` : undefined,
		'translations.name': q || undefined,
		...filterQuery(filters),
		...SORTS[sort].query,
		page,
		itemsPerPage: perPage
	});

	return {
		items: items.map((p) => toSummary(p, viewer)),
		total,
		page,
		pages: Math.max(1, Math.ceil(total / perPage))
	};
}

/**
 * Name search of the catalogue API: products whose name contains `q`, the product with code `q`
 * first. Search page fallback when the search API cannot answer (#lib/server/search.ts).
 */
export async function searchProducts(api: ShopClient, viewer: Viewer, params: ListingParams): Promise<ProductPage> {
	const [results, exact] = await Promise.all([
		listProducts(api, viewer, params),
		params.page === 1 && /^[\w-]+$/.test(params.q) ? api.find<ApiProduct>(`/products/${encodeURIComponent(params.q.toUpperCase())}`) : null
	]);
	if (!exact || results.items.some((p) => p.code === exact.code)) return results;

	return { ...results, items: [toSummary(exact, viewer), ...results.items], total: results.total + 1 };
}

/** Product cards of these codes, in this order (an unknown or disabled code is left out). */
export async function productsByCode(api: ShopClient, viewer: Viewer, codes: string[]): Promise<ProductSummary[]> {
	const products = await Promise.all(codes.map((code) => api.find<ApiProduct>(`/products/${encodeURIComponent(code)}`)));
	return products.filter((p) => p !== null).map((p) => toSummary(p, viewer));
}

/** "Derniers produits" (as the Sylius homepage): the 8 most recent products. */
export async function latestProducts(api: ShopClient, viewer: Viewer, count = 8): Promise<ProductSummary[]> {
	const items = await api.get<ApiProduct[]>('/products', { 'order[createdAt]': 'desc', itemsPerPage: count });
	return items.map((p) => toSummary(p, viewer));
}

export interface FoundProduct {
	product: ProductDetail;
	/** Up to `limit` associated products: Sylius associations first, completed by the same main category. */
	related(limit: number): Promise<ProductSummary[]>;
}

/** Product by slug (product page) or code (builder sample); null when unknown / disabled. */
export async function findProduct(api: ShopClient, viewer: Viewer, by: { slug: string } | { code: string }): Promise<FoundProduct | null> {
	// products-by-slug answers with a redirect to /products/{code}, followed by fetch.
	const product = await api.find<ApiProduct>(
		'slug' in by ? `/products-by-slug/${encodeURIComponent(by.slug)}` : `/products/${encodeURIComponent(by.code)}`
	);
	if (!product) return null;

	return {
		product: toDetail(product, viewer),
		related: async (limit) => (limit > 0 ? (await relatedProducts(api, product, limit)).map((p) => toSummary(p, viewer)) : [])
	};
}

async function relatedProducts(api: ShopClient, product: ApiProduct, limit: number): Promise<ApiProduct[]> {
	const associations = await Promise.all(
		(product.associations ?? []).map((iri) => api.find<{ associatedProducts?: string[] }>(`/product-associations/${iriCode(iri)}`))
	);
	const codes = [...new Set(associations.flatMap((a) => a?.associatedProducts ?? []).map(iriCode))]
		.filter((code) => code !== product.code)
		.slice(0, limit);
	const related = (await Promise.all(codes.map((code) => api.find<ApiProduct>(`/products/${encodeURIComponent(code)}`)))).filter(
		(p) => p !== null
	);

	if (related.length < limit && product.mainTaxon) {
		const siblings = await api.get<ApiProduct[]>('/products', { taxon: product.mainTaxon, itemsPerPage: limit + 1 + related.length });
		for (const sibling of siblings) {
			if (related.length >= limit) break;
			if (sibling.code !== product.code && !related.some((p) => p.code === sibling.code)) related.push(sibling);
		}
	}
	return related;
}

// ── Taxon tree ──

/** Catalog sections of the front: taxon tree root → front URL. */
export const SECTIONS = [
	{ code: 'clik_yack', url: '/produits', label: 'Clik&Yack', type: 'product' },
	{ code: 'clik_yack_parts', url: '/pieces', label: 'Clik&Yack Parts', type: 'part' }
] as const satisfies { code: string; url: string; label: string; type: ProductType }[];

async function taxonNode(api: ShopClient, code: string, url: string, rootSlug: string | null): Promise<TaxonNode | null> {
	const taxon = await api.find<ApiTaxon>(`/taxons/${encodeURIComponent(code)}`);
	if (!taxon) return null;
	const base = rootSlug ?? taxon.slug;
	const nodeUrl = rootSlug ? `${url}${taxon.slug.slice(base.length)}` : url;
	const children = await Promise.all(taxon.children.map((iri) => taxonNode(api, iriCode(iri), url, base)));

	return {
		code: taxon.code,
		name: taxon.name,
		description: taxon.description,
		slug: taxon.slug,
		url: nodeUrl,
		children: children.filter((c) => c !== null)
	};
}

/**
 * Clik&Yack and Clik&Yack Parts trees (public data, about 60 API calls to build): kept in memory,
 * refreshed in the background after a minute (#lib/server/staleCache.ts), so no shopper waits for it
 * once the first build is done.
 */
const tree = staleCache(async () => {
	const api = shopClient(null);
	const roots = await Promise.all(SECTIONS.map((s) => taxonNode(api, s.code, s.url, null)));
	return roots.filter((r) => r !== null);
}, 60_000, {
	onRefreshError: (error) => console.warn('Arbre du catalogue : mise à jour en arrière-plan en échec, ancien arbre gardé.', error)
});

export function catalogTree(): Promise<TaxonNode[]> {
	return tree.get();
}

/** Path from a root to the taxon matching `match` (empty when not found). */
export function taxonPath(nodes: TaxonNode[], match: (node: TaxonNode) => boolean): TaxonNode[] {
	for (const node of nodes) {
		if (match(node)) return [node];
		const path = taxonPath(node.children, match);
		if (path.length > 0) return [node, ...path];
	}
	return [];
}

export function breadcrumb(path: TaxonNode[], current?: string): Breadcrumb[] {
	return [
		{ label: 'Accueil', url: '/' },
		...path.map((node, i) => ({ label: node.name, url: i === path.length - 1 && !current ? null : node.url })),
		...(current ? [{ label: current, url: null }] : [])
	];
}

// ── CMS ──

export const publishedPage = (api: ShopClient, slug: string): Promise<CmsPage | null> =>
	api.find<CmsPage>(`/cms-pages/${encodeURIComponent(slug)}`);

export const publishedPages = (api: ShopClient): Promise<CmsPageSummary[]> => api.get<CmsPageSummary[]>('/cms-pages');

/** Products / taxons referenced by CMS blocks (unknown or disabled codes are skipped). */
export async function blockCatalog(
	api: ShopClient,
	viewer: Viewer,
	productCodes: string[],
	taxonCodes: string[]
): Promise<Catalog> {
	const [products, roots] = await Promise.all([
		Promise.all(productCodes.map((code) => api.find<ApiProduct>(`/products/${encodeURIComponent(code)}`))),
		taxonCodes.length > 0 ? catalogTree() : []
	]);
	const taxons = taxonCodes
		.map((code) => taxonPath(roots, (n) => n.code === code).at(-1))
		.filter((node) => node !== undefined)
		.map((node): TaxonCard => ({ code: node.code, name: node.name, url: node.url }));

	return {
		products: Object.fromEntries(products.filter((p) => p !== null).map((p) => [p.code, toSummary(p, viewer)])),
		taxons: Object.fromEntries(taxons.map((t) => [t.code, t]))
	};
}
