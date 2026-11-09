// Shop data shared by server loads and components (shapes of docs/API_FRONT.md, mapped by
// #lib/server/catalog.ts). Amounts are integers in cents (Sylius), formatted by #lib/format.ts.

export type Profile = 'visitor' | 'pro' | 'ecom';

export interface OrderAccessRequest {
	status: 'pending' | 'accepted' | 'refused';
	statusLabel: string;
	createdAt: string;
	decidedAt: string | null;
}

export interface BillingAddress {
	company: string | null;
	street: string;
	postcode: string;
	city: string;
	countryCode: string;
}

/** The person browsing (GET /api/v2/shop/yack/me): profile and what it allows. */
export interface Viewer {
	authenticated: boolean;
	/** Sylius customer id (/shop/customers/{id}), null for a visitor. */
	customerId: number | null;
	profile: Profile;
	profileLabel: string;
	canSeePrices: boolean;
	canOrder: boolean;
	canRequestOrderAccess: boolean;
	email: string | null;
	firstName: string | null;
	lastName: string | null;
	phoneNumber: string | null;
	company: string | null;
	siret: string | null;
	vatNumber: string | null;
	billingAddress: BillingAddress | null;
	orderAccessRequest: OrderAccessRequest | null;
}

/** Same answer as /yack/me without JWT (no API call needed). */
export const VISITOR: Viewer = {
	authenticated: false,
	customerId: null,
	profile: 'visitor',
	profileLabel: 'Visiteur',
	canSeePrices: false,
	canOrder: false,
	canRequestOrderAccess: false,
	email: null,
	firstName: null,
	lastName: null,
	phoneNumber: null,
	company: null,
	siret: null,
	vatNumber: null,
	billingAddress: null,
	orderAccessRequest: null
};

export type ProductType = 'product' | 'part';

/** Stock level of the API (`yack.stock.level`, Catalog `StockLevel`): the front holds no threshold. */
export type StockLevel = 'en_stock' | 'faible' | 'sur_commande' | 'indisponible';

export interface ProductStock {
	level: StockLevel;
	/** Availability label of the API ("En stock", "Sur commande", "Indisponible", "Plus commercialisée"). */
	label: string;
	/** Units available (tracked stock), null otherwise. */
	available: number | null;
}

export interface ProductSummary {
	code: string;
	name: string;
	slug: string;
	/** Front URL (/produit/<slug>). */
	url: string;
	/** Thumbnail (relative /media URL), null without image. */
	image: string | null;
	/** Cents; null when the viewer may not see prices. */
	price: number | null;
	originalPrice: number | null;
	type: ProductType | null;
	typeLabel: string | null;
	brand: string | null;
	inStock: boolean | null;
	stockLabel: string | null;
	/** Stock of the variant sold, null when the API gives none. */
	stock: ProductStock | null;
	/** Code of the variant sold (Yack products have a single variant): "Ajouter au panier". */
	variantCode: string | null;
	/** False for a product not sold online (`yack.soldOnline`): no price, no cart, for every profile. */
	soldOnline: boolean;
}

export interface ProductAttribute {
	code: string;
	name: string;
	value: string;
}

export interface ProductImage {
	thumbnail: string;
	large: string;
}

export interface ProductDetail extends ProductSummary {
	description: string | null;
	shortDescription: string | null;
	range: string | null;
	/** Gallery (Sylius images of type main / thumbnail / none). */
	images: ProductImage[];
	/** Other typed Sylius images of the product (notice, fiche-technique…): downloadable documents. */
	documents: { label: string; url: string }[];
	attributes: ProductAttribute[];
	mainTaxonCode: string | null;
}

export type SortKey = 'position' | 'nom-asc' | 'nom-desc' | 'recent' | 'ancien' | 'prix-asc' | 'prix-desc';

export interface SortOption {
	key: SortKey;
	label: string;
}

/** Facets of a listing (`GET /shop/yack/catalog-facets`), in the order of the API. */
export type FacetCode = 'brand' | 'range' | 'power' | 'refrigerant';

/** URL parameter of each facet filter (one value each: the API filters take a single value). */
export const FILTER_PARAMS = { brand: 'marque', range: 'gamme', power: 'puissance', refrigerant: 'fluide' } as const satisfies Record<FacetCode, string>;

export interface FacetValue {
	/** Filter value (power: band code "6-9"…). */
	value: string;
	label: string;
	/** Products of the listing with this value, the other facets applied. */
	count: number;
}

export interface Facet {
	code: FacetCode;
	label: string;
	values: FacetValue[];
}

/** Facets of a listing; `counted`: the counts match the listing (section root, no text search). */
export interface ListingFacets {
	facets: Facet[];
	counted: boolean;
}

/** Facet filters of a listing (absent = not filtered). */
export type ListingFilters = Partial<Record<FacetCode, string>>;

/**
 * Raw listing query of the URL (?q=&tri=&page=&marque=&gamme=&puissance=&fluide=), argument of the
 * listing queries (#lib/remote/catalog.remote.ts). ?par= of an old link is not read: the page size
 * comes from the category template or the listing defaults.
 */
export interface ListingSearch {
	q?: string;
	tri?: string;
	page?: string;
	marque?: string;
	gamme?: string;
	puissance?: string;
	fluide?: string;
}

const SEARCH_PARAMS = ['q', 'tri', 'page', ...Object.values(FILTER_PARAMS)] as const;

/** `ListingSearch` of a URL (absent parameters omitted: one query cache entry per listing state). */
export function listingSearch(url: { searchParams: Pick<URLSearchParams, 'get'> }): ListingSearch {
	const search: ListingSearch = {};
	for (const name of SEARCH_PARAMS) {
		const value = url.searchParams.get(name);
		if (value) search[name] = value;
	}
	return search;
}

/** Listing state: ?q=&tri=&page= and the facet filters of the URL, page size of the template or the defaults. */
export interface ListingParams {
	q: string;
	sort: SortKey;
	perPage: number;
	page: number;
	filters: ListingFilters;
}

export interface ProductPage {
	items: ProductSummary[];
	total: number;
	page: number;
	pages: number;
	/** Facets of the listing (category pages only). */
	facets?: ListingFacets;
}

/** Taxon of the catalog tree, with its front URL (/produits/…, /pieces/…). */
export interface TaxonNode {
	code: string;
	name: string;
	description: string | null;
	/** Sylius slug (old Twig URL /fr_FR/taxons/{slug}, redirected to `url`). */
	slug: string;
	url: string;
	children: TaxonNode[];
}

export interface Breadcrumb {
	label: string;
	url: string | null;
}

/** Header cart widget (ECOM). */
export interface CartSummary {
	itemsCount: number;
	itemsTotal: number;
}
