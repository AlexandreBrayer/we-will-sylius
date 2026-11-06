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
	images: ProductImage[];
	attributes: ProductAttribute[];
	/** Code of the variant sold (Yack products have a single variant). */
	variantCode: string | null;
	mainTaxonCode: string | null;
}

export type SortKey = 'position' | 'nom-asc' | 'nom-desc' | 'recent' | 'ancien' | 'prix-asc' | 'prix-desc';

export interface SortOption {
	key: SortKey;
	label: string;
}

/** Listing state, read from the URL: ?q=&tri=&par=&page= */
export interface ListingParams {
	q: string;
	sort: SortKey;
	perPage: number;
	page: number;
}

export interface ProductPage {
	items: ProductSummary[];
	total: number;
	page: number;
	pages: number;
}

/** Taxon of the catalog tree, with its front URL (/produits/…, /pieces/…). */
export interface TaxonNode {
	code: string;
	name: string;
	description: string | null;
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
