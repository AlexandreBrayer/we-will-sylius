// Catalog of the shop (products, taxon tree, CMS pages) read from the Sylius shop API and mapped
// to the front types (#lib/types.ts). Prices come from the API, which hides them from visitors
// and PRO accounts; components still gate on viewer.canSeePrices.
import type { Catalog, CmsPage, CmsPageSummary, TaxonCard } from '#lib/blocks/types.js';
import type {
	Breadcrumb,
	ListingParams,
	ListingSearch,
	ProductAttribute,
	ProductPage,
	ProductDetail,
	ProductSummary,
	ProductType,
	SortKey,
	SortOption,
	StockLevel,
	TaxonNode,
	Viewer
} from '#lib/types.js';
import { shopClient, type Query, type ShopClient } from './sylius.js';

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

/** Sylius image types of the product gallery; any other type (notice, fiche-technique…) is a document. */
const GALLERY_TYPES = [null, '', 'main', 'thumbnail'];

function toDetail(product: ApiProduct, viewer: Viewer): ProductDetail {
	const attributes: ProductAttribute[] = (product.yack?.attributes ?? [])
		.map((a) => ({ code: a.code, name: a.name, value: attributeValue(a.value) }))
		.filter((a) => a.value !== '');
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
export const PER_PAGE = [9, 18, 27] as const;

/** Initial sort / page size of a listing (URL parameters equal to them are dropped). */
export const LISTING_DEFAULTS: { sort: SortKey; perPage: number } = { sort: 'position', perPage: PER_PAGE[0] };

/**
 * Listing parameters from the URL query (?q=&tri=&par=&page=, see `listingSearch`); absent or
 * invalid values → `defaults` (initial sort / page size of a category template).
 */
export function listingParams(search: ListingSearch, viewer: Viewer, defaults = LISTING_DEFAULTS): ListingParams {
	const perPage = Number(search.par);
	const page = Number(search.page);
	const validSort = sortOptions(viewer).find((option) => option.key === search.tri)?.key ?? defaults.sort;

	return {
		q: (search.q ?? '').trim(),
		sort: validSort,
		perPage: (PER_PAGE as readonly number[]).includes(perPage) ? perPage : defaults.perPage,
		page: Number.isInteger(page) && page > 1 ? page : 1
	};
}

/** Products of a taxon (descendants included) and/or matching a name, sorted and paginated. */
export async function listProducts(
	api: ShopClient,
	viewer: Viewer,
	{ taxonCode, q, sort, page, perPage }: ListingParams & { taxonCode?: string }
): Promise<ProductPage> {
	const { items, total } = await api.collection<ApiProduct>('/products', {
		taxon: taxonCode ? `/api/v2/shop/taxons/${taxonCode}` : undefined,
		'translations.name': q || undefined,
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

/** Search box: products whose name contains `q`, the product with code `q` first. */
export async function searchProducts(api: ShopClient, viewer: Viewer, params: ListingParams): Promise<ProductPage> {
	const [results, exact] = await Promise.all([
		listProducts(api, viewer, params),
		params.page === 1 && /^[\w-]+$/.test(params.q) ? api.find<ApiProduct>(`/products/${encodeURIComponent(params.q.toUpperCase())}`) : null
	]);
	if (!exact || results.items.some((p) => p.code === exact.code)) return results;

	return { ...results, items: [toSummary(exact, viewer), ...results.items], total: results.total + 1 };
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
	{ code: 'clik_yack', url: '/produits', label: 'Clik&Yack' },
	{ code: 'clik_yack_parts', url: '/pieces', label: 'Clik&Yack Parts' }
] as const;

const TREE_TTL = 60_000;
let tree: { loadedAt: number; promise: Promise<TaxonNode[]> } | null = null;

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

/** Clik&Yack and Clik&Yack Parts trees (public data, cached in memory for a minute). */
export function catalogTree(): Promise<TaxonNode[]> {
	if (!tree || Date.now() - tree.loadedAt > TREE_TTL) {
		const api = shopClient(null);
		const promise = Promise.all(SECTIONS.map((s) => taxonNode(api, s.code, s.url, null))).then((roots) =>
			roots.filter((r) => r !== null)
		);
		promise.catch(() => (tree = null));
		tree = { loadedAt: Date.now(), promise };
	}
	return tree.promise;
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
