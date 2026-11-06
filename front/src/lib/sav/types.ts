// After-sales (SAV) data: serial number → models (ServiceCode) → exploded views → parts.
// Shapes of GET /api/v2/shop/yack/sav/* (docs/API_FRONT.md §7). Prices in cents HT, null unless ECOM.

/** Demo serial numbers (docs/POC_YACK.md), suggested under the search field. */
export const DEMO_SERIALS = ['YK25EVO08M001234', 'YK19KAZ35S004567', 'YK24HYD11S000321', 'YK22THD200000789'] as const;

export interface Warranty {
	serialNumber: string;
	commissionedAt: string;
	partsCoverageEnd: string;
	compressorCoverageEnd: string;
	/** Coverage at today's date. */
	partsCovered: boolean;
	compressorCovered: boolean;
	source: string;
}

export interface FinishedProduct {
	code: string;
	slug: string;
	name: string;
}

export interface SavModelSummary {
	serviceCode: string;
	name: string;
	family: string;
	explodedViewCount: number;
	partCount: number;
	finishedProduct: FinishedProduct | null;
}

/** GET yack/sav/serials/{serial or ServiceCode}. */
export interface SavSerial {
	query: string;
	/** Normalized serial number, null when a ServiceCode was typed. */
	serial: string | null;
	isServiceCode: boolean;
	/** null for a ServiceCode. */
	warranty: Warranty | null;
	models: SavModelSummary[];
}

export interface ExplodedViewSummary {
	id: number;
	name: string;
	imagePath: string;
	imageUrl: string;
	partCount: number;
}

/** GET yack/sav/models/{serviceCode}. */
export interface SavModel {
	serviceCode: string;
	name: string;
	family: string;
	partCount: number;
	finishedProduct: FinishedProduct | null;
	explodedViews: ExplodedViewSummary[];
}

export interface PartStock {
	tracked: boolean;
	available: number;
	inStock: boolean;
	label: string;
}

export interface SavPart {
	code: string;
	name: string;
	slug: string;
	enabled: boolean;
	/** IRI of the variant, null = cannot be ordered. */
	variant: string | null;
	variantCode: string | null;
	/** Cents HT; null for visitors and PRO accounts (and parts no longer sold). */
	price: number | null;
	stock: PartStock;
}

export interface ExplodedViewItem {
	marker: number;
	/** Hotspot position, in % of the image. */
	x: number;
	y: number;
	quantity: number;
	part: SavPart;
}

/** GET yack/sav/exploded-views/{id}. */
export interface ExplodedView {
	id: number;
	name: string;
	imagePath: string;
	imageUrl: string;
	model: { serviceCode: string; name: string; family: string };
	/** All the views of the model (view switcher). */
	views: { id: number; name: string }[];
	items: ExplodedViewItem[];
}

/** URL segment identifying the device searched: the normalized serial, or the ServiceCode typed. */
export function savKey(result: SavSerial): string {
	return result.serial ?? result.models[0]?.serviceCode ?? result.query;
}

export const savUrl = (key: string, serviceCode?: string, viewId?: number): string =>
	['/sav', key, serviceCode, viewId]
		.filter((segment) => segment !== undefined)
		.map((segment, i) => (i === 0 ? segment : encodeURIComponent(String(segment))))
		.join('/');
