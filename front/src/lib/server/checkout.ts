// Checkout of the ECOM cart (Sylius flow: address → shipping → payment → complete, docs/API_FRONT.md §9).
// Remote adapters: #lib/remote/checkout.remote.ts.
import { error, redirect } from '@sveltejs/kit';
import { CHECKOUT_STEPS, reachableStep, stepPosition, type CheckoutStep } from '#lib/order/checkout.js';
import {
	iriCode,
	orderLines,
	type Order,
	type OrderAddress,
	type OrderLine,
	type PaymentMethodChoice,
	type SavedAddress,
	type ShippingMethodChoice
} from '#lib/order/types.js';
import { cartCheckedOut, cartUpdated, currentCart } from './cart.js';
import { loginUrl, pageUrl } from './session.js';
import { sylius, type ShopClient, type ShopEvent } from './sylius.js';

type CheckoutEvent = ShopEvent;

/**
 * Cart being checked out. Visitor → login; PRO → the cart page explains why ordering is closed;
 * empty cart → cart page.
 */
export async function checkoutCart(event: CheckoutEvent): Promise<Order> {
	const { viewer } = event.locals;
	if (!viewer.authenticated) redirect(303, loginUrl(pageUrl(event)));
	if (!viewer.canOrder) redirect(303, '/panier');

	const cart = await currentCart(event);
	if (!cart || cart.items.length === 0) redirect(303, '/panier');
	return cart;
}

/** A step not reached yet (e.g. typed URL) sends back to the furthest step the cart may open. */
export function guardStep(cart: Order, step: CheckoutStep): void {
	const reachable = reachableStep(cart.checkoutState);
	if (stepPosition(step) > stepPosition(reachable)) {
		redirect(303, CHECKOUT_STEPS.find((s) => s.name === reachable)?.href ?? '/panier');
	}
}

export interface CheckoutView {
	order: Order;
	lines: OrderLine[];
}

/** Cart of a checkout step (guarded): the summary of the tunnel layout and the step pages. */
export async function checkoutView(event: CheckoutEvent, step: CheckoutStep): Promise<CheckoutView> {
	const order = await checkoutCart(event);
	guardStep(order, step);
	return { order, lines: orderLines(order) };
}

// --- Step 1: addresses ---------------------------------------------------------------------------

export const ADDRESS_FIELDS = ['firstName', 'lastName', 'company', 'street', 'postcode', 'city', 'countryCode', 'phoneNumber'] as const;

export type AddressValues = Record<(typeof ADDRESS_FIELDS)[number], string>;

/** Address of a section of the form: a saved address id (`choice`) or "new" with the typed fields. */
export type AddressInput = { choice: string } & AddressValues;

const REQUIRED: Partial<Record<(typeof ADDRESS_FIELDS)[number], string>> = {
	firstName: 'le prénom',
	lastName: 'le nom',
	street: "l'adresse (numéro et voie)",
	postcode: 'le code postal',
	city: 'la ville',
	countryCode: 'le pays'
};

const countryNames = new Intl.DisplayNames(['fr'], { type: 'region' });

/** Same address, whatever the phone formatting ("04 42 00 00 10" = "0442000010"). */
const addressKey = (a: OrderAddress): string =>
	[a.firstName, a.lastName, a.company ?? '', a.street, a.postcode, a.city, a.countryCode, (a.phoneNumber ?? '').replace(/\D/g, '')]
		.map((part) => part.trim().toLowerCase())
		.join('|');

const toAddress = (a: OrderAddress): OrderAddress => ({
	firstName: a.firstName,
	lastName: a.lastName,
	company: a.company || null,
	street: a.street,
	postcode: a.postcode,
	city: a.city,
	countryCode: a.countryCode,
	phoneNumber: a.phoneNumber || null
});

const toValues = (a: OrderAddress | null): AddressValues =>
	Object.fromEntries(ADDRESS_FIELDS.map((field) => [field, a?.[field] ?? (field === 'countryCode' ? 'FR' : '')])) as AddressValues;

/** Address book of the customer, duplicates (same address saved by several orders) removed. */
async function addressBook(api: ShopClient): Promise<SavedAddress[]> {
	const seen = new Set<string>();
	return (await api.get<SavedAddress[]>('/addresses')).filter((address) => {
		const key = addressKey(address);
		if (seen.has(key)) return false;
		seen.add(key);
		return true;
	});
}

/** Pre-selection: the saved address equal to `current`, else "new" pre-filled with it, else the first saved one. */
function choice(book: SavedAddress[], current: OrderAddress | null): string {
	if (current) return String(book.find((a) => addressKey(a) === addressKey(current))?.id ?? 'new');
	return book.length > 0 ? String(book[0].id) : 'new';
}

export interface AddressStep {
	book: SavedAddress[];
	countries: { code: string; name: string }[];
	shipping: AddressInput;
	billing: AddressInput;
	sameBilling: boolean;
}

export async function addressStep(event: CheckoutEvent): Promise<AddressStep> {
	const order = await checkoutCart(event);
	const api = sylius(event);
	const [book, countries] = await Promise.all([addressBook(api), api.get<{ code: string }[]>('/countries')]);

	// The API pre-fills the billing address with the company address of the account.
	const shipping = order.shippingAddress ?? order.billingAddress;
	const billing = order.billingAddress ?? shipping;

	return {
		book,
		countries: countries.map(({ code }) => ({ code, name: countryNames.of(code) ?? code })).sort((a, b) => a.name.localeCompare(b.name, 'fr')),
		shipping: { choice: choice(book, order.shippingAddress ?? (book.length > 0 ? null : shipping)), ...toValues(shipping) },
		billing: { choice: choice(book, billing), ...toValues(billing) },
		sameBilling: !order.billingAddress || !shipping || addressKey(order.billingAddress) === addressKey(shipping)
	};
}

/** Address to send for a section, or the French message explaining what is missing. */
function readAddress(input: AddressInput, book: SavedAddress[]): OrderAddress | string {
	if (input.choice !== 'new') {
		const saved = book.find((a) => String(a.id) === input.choice);
		return saved ? toAddress(saved) : "Cette adresse n'existe plus dans votre carnet.";
	}
	const values = Object.fromEntries(ADDRESS_FIELDS.map((field) => [field, (input[field] ?? '').trim()])) as AddressValues;
	const missing = Object.entries(REQUIRED).filter(([field]) => !values[field as keyof AddressValues]).map(([, label]) => label);
	if (missing.length > 0) return `Indiquez ${missing.join(', ')}.`;
	return toAddress(values as unknown as OrderAddress);
}

/**
 * PUT /shop/orders/{token}: shipping + billing addresses (checkoutState → addressed).
 * Returns the French message of an incomplete address, null once saved (API refusals are thrown).
 */
export async function saveAddresses(event: CheckoutEvent, shipping: AddressInput, billing: AddressInput | null): Promise<string | null> {
	const order = await checkoutCart(event);
	const api = sylius(event);
	const book = await addressBook(api);

	const shippingAddress = readAddress(shipping, book);
	if (typeof shippingAddress === 'string') return `Adresse de livraison : ${shippingAddress}`;
	const billingAddress = billing ? readAddress(billing, book) : shippingAddress;
	if (typeof billingAddress === 'string') return `Adresse de facturation : ${billingAddress}`;

	const updated = await api.put<Order>(`/orders/${order.tokenValue}`, {
		email: event.locals.viewer.email ?? order.customer?.email,
		shippingAddress,
		billingAddress
	});
	cartUpdated(event, updated);
	return null;
}

// --- Step 2: shipping ----------------------------------------------------------------------------

export async function shippingStep(event: CheckoutEvent): Promise<{ methods: ShippingMethodChoice[]; selected: string | null }> {
	const order = await checkoutCart(event);
	guardStep(order, 'select_shipping');
	const shipment = order.shipments[0];
	if (!shipment) redirect(303, '/commande/paiement');

	const methods = await sylius(event).get<ShippingMethodChoice[]>(`/orders/${order.tokenValue}/shipments/${shipment.id}/methods`);
	return { methods, selected: iriCode(shipment.method) ?? methods[0]?.code ?? null };
}

/** PATCH /shop/orders/{token}/shipments/{id} (checkoutState → shipping_selected). */
export async function chooseShipping(event: CheckoutEvent, method: string): Promise<void> {
	const order = await checkoutCart(event);
	const shipment = order.shipments[0];
	if (!shipment) error(409, 'Aucune livraison à choisir pour cette commande.');
	cartUpdated(event, await sylius(event).patch<Order>(`/orders/${order.tokenValue}/shipments/${shipment.id}`, { shippingMethod: method }));
}

// --- Step 3: payment -----------------------------------------------------------------------------

export async function paymentStep(event: CheckoutEvent): Promise<{ methods: PaymentMethodChoice[]; selected: string | null }> {
	const order = await checkoutCart(event);
	guardStep(order, 'select_payment');
	const payment = order.payments[0];
	if (!payment) redirect(303, '/commande/recapitulatif');

	const methods = await sylius(event).get<PaymentMethodChoice[]>(`/orders/${order.tokenValue}/payments/${payment.id}/methods`);
	// Sylius pre-selects the first method (Virement sous 15 jours) and applies its escompte as soon
	// as the delivery is chosen: the summary already shows it on arrival.
	return { methods, selected: iriCode(payment.method) ?? methods[0]?.code ?? null };
}

/**
 * PATCH /shop/orders/{token}/payments/{id} (checkoutState → payment_selected): recomputes
 * escompte and VAT. The request cache of the cart is replaced by the updated order.
 */
export async function choosePayment(event: CheckoutEvent, method: string): Promise<void> {
	const order = await checkoutCart(event);
	const payment = order.payments[0];
	if (!payment) error(409, 'Aucun paiement à choisir pour cette commande.');
	cartUpdated(event, await sylius(event).patch<Order>(`/orders/${order.tokenValue}/payments/${payment.id}`, { paymentMethod: method }));
}

// --- Step 4: complete ----------------------------------------------------------------------------

/**
 * PATCH /shop/orders/{token}/complete: the order is placed, confirmation e-mail sent by Sylius.
 * The cart cookie is dropped in the same response; returns the token of the placed order.
 */
export async function completeOrder(event: CheckoutEvent, notes: string): Promise<string> {
	const order = await checkoutCart(event);
	guardStep(order, 'complete');
	await sylius(event).patch(`/orders/${order.tokenValue}/complete`, { notes: notes || null });
	cartCheckedOut(event);
	return order.tokenValue;
}

/** Placed order of the confirmation page (`/commande/confirmation/[token]`). */
export async function confirmedOrder(event: CheckoutEvent, token: string): Promise<CheckoutView> {
	const { viewer } = event.locals;
	if (!viewer.authenticated) redirect(303, loginUrl(pageUrl(event)));
	if (!viewer.canOrder) error(404, 'Commande introuvable');

	const order = await sylius(event).find<Order>(`/orders/${encodeURIComponent(token)}`);
	if (!order || order.checkoutState !== 'completed') error(404, 'Commande introuvable');
	// Card not paid yet (refused, abandoned): the payment page of the order, not a thank-you page.
	if (order.yack.paymentMethod?.card && order.yack.payable) redirect(303, `/commande/reglement/${encodeURIComponent(token)}`);
	return { order, lines: orderLines(order) };
}
