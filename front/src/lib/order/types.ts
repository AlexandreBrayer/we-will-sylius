// Sylius cart / order of the shop API with its Yack block (docs/API_FRONT.md §9).
// Amounts are integers in cents; `yack.totals` is the breakdown to display (the native
// shippingTotal / shippingTaxTotal labels are misleading with the escompte).

export interface OrderAddress {
	firstName: string;
	lastName: string;
	company: string | null;
	street: string;
	postcode: string;
	city: string;
	countryCode: string;
	phoneNumber: string | null;
}

/** Address book entry (GET /shop/addresses). */
export interface SavedAddress extends OrderAddress {
	id: number;
}

/** Native order line. `unitPrice` / `subtotal` are excl. tax, `total` incl. tax. */
export interface OrderItem {
	id: number;
	variant: string;
	productName: string;
	variantName: string | null;
	quantity: number;
	unitPrice: number;
	originalUnitPrice: number;
	discountedUnitPrice: number;
	subtotal: number;
	total: number;
}

/** Catalogue data of a line (`yack.items`, same order and `id` as `items`). */
export interface OrderItemInfo {
	id: number;
	variantCode: string | null;
	productCode: string | null;
	productSlug: string | null;
	/** Absolute thumbnail URL built with the host Sylius was called with: keep the pathname. */
	image: string | null;
	type: 'product' | 'part' | null;
	typeLabel: string | null;
}

export interface OrderTotals {
	/** Goods excl. tax, before promotion and escompte. */
	itemsExclTax: number;
	promotion: number;
	/** Negative (or 0). */
	escompte: number;
	itemsNetExclTax: number;
	shippingExclTax: number;
	itemsTax: number;
	shippingTax: number;
	taxTotal: number;
	totalExclTax: number;
	/** Incl. tax. */
	total: number;
}

export interface OrderPaymentMethod {
	code: string;
	name: string;
	escompteRate: number | null;
	escompteLabel: string | null;
}

export interface BankAccount {
	holder: string;
	bank: string;
	iban: string;
	bic: string;
}

/** Once the order is placed: bank transfer details, or card payment accepted. */
export interface PaymentInstruction {
	kind: 'virement' | 'carte';
	amount: number;
	paid: boolean;
	bankAccount: BankAccount | null;
	reference: string | null;
	/** Y-m-d. */
	dueDate: string | null;
}

export interface OrderState {
	state: string;
	label: string;
}

export interface OrderShipment {
	id: number;
	method: { code: string; name: string } | null;
	state: string;
	tracking: string | null;
	shippedAt: string | null;
}

export interface YackOrder {
	totals: OrderTotals;
	escompte: { label: string; amount: number } | null;
	paymentMethod: OrderPaymentMethod | null;
	paymentInstruction: PaymentInstruction | null;
	paymentStatus: OrderState;
	shippingStatus: OrderState;
	shipments: OrderShipment[];
	items: OrderItemInfo[];
}

export type CheckoutState = 'cart' | 'addressed' | 'shipping_selected' | 'shipping_skipped' | 'payment_selected' | 'payment_skipped' | 'completed';

/** GET /shop/orders/{tokenValue} (application/json). */
export interface Order {
	tokenValue: string;
	number: string | null;
	state: 'cart' | 'new' | 'fulfilled' | 'cancelled';
	checkoutState: CheckoutState;
	paymentState: string;
	shippingState: string;
	checkoutCompletedAt: string | null;
	customer: { email: string } | null;
	billingAddress: OrderAddress | null;
	shippingAddress: OrderAddress | null;
	/** Ids and chosen method IRIs (`/api/v2/shop/shipping-methods/<code>`). */
	shipments: { id: number; method: string | null }[];
	payments: { id: number; method: string | null }[];
	items: OrderItem[];
	itemsTotal: number;
	total: number;
	yack: YackOrder;
}

/** Shipping method offered for a shipment (`price` in cents excl. tax). */
export interface ShippingMethodChoice {
	code: string;
	name: string;
	description: string | null;
	price: number;
}

/** Payment method offered for a payment, with its escompte. */
export interface PaymentMethodChoice {
	code: string;
	name: string;
	description: string | null;
	instructions: string | null;
	escompteRate: number | null;
	escompteLabel: string | null;
}

/** Order line ready to display (native line + catalogue data). */
export interface OrderLine {
	id: number;
	name: string;
	code: string | null;
	url: string | null;
	image: string | null;
	typeLabel: string | null;
	type: 'product' | 'part' | null;
	quantity: number;
	/** Excl. tax. */
	unitPrice: number;
	/** Excl. tax (quantity × unit price, after promotions). */
	subtotal: number;
}

/** Native lines joined with `yack.items` (catalogue data). */
export function orderLines(order: Order): OrderLine[] {
	const infos = new Map(order.yack.items.map((info) => [info.id, info]));
	return order.items.map((item) => {
		const info = infos.get(item.id);
		return {
			id: item.id,
			name: item.productName,
			code: info?.productCode ?? info?.variantCode ?? null,
			url: info?.productSlug ? `/produit/${info.productSlug}` : null,
			image: info?.image ? new URL(info.image, 'http://localhost').pathname : null,
			typeLabel: info?.typeLabel ?? null,
			type: info?.type ?? null,
			quantity: item.quantity,
			unitPrice: item.unitPrice,
			subtotal: item.subtotal
		};
	});
}

/** Code of an API resource IRI (`/api/v2/shop/payment-methods/virement_15j` → `virement_15j`). */
export const iriCode = (iri: string | null): string | null => (iri ? decodeURIComponent(iri.slice(iri.lastIndexOf('/') + 1)) : null);
