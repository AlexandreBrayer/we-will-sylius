// ECOM cart (Sylius order in state "cart"), identified by its tokenValue in the yack_cart cookie.
// The Sylius API reserves carts to ECOM accounts (visitor 401, PRO 403). There is no visitor cart
// to merge at login: the customer gets back the latest non-empty cart Sylius kept for them
// (POST /shop/orders picks it up), otherwise a new one.
import { orderLines, type Order, type OrderLine, type OrderTotals, type YackOrder } from '#lib/order/types.js';
import type { CartSummary } from '#lib/types.js';
import { clearCartToken, setCartToken } from './session.js';
import { ApiError, sylius, type ShopClient, type ShopEvent } from './sylius.js';

type CartEvent = ShopEvent;

export const EMPTY_CART: CartSummary = { itemsCount: 0, itemsTotal: 0 };

const summary = (cart: Order): CartSummary => ({
	itemsCount: cart.items.reduce((count, item) => count + item.quantity, 0),
	itemsTotal: cart.itemsTotal
});

/** One cart load per request: the header, the page and the mutations of a request share it. */
const loaded = new WeakMap<App.Locals, Promise<Order | null>>();

/** The cart changed during the request (API answer): later reads of the request see it. */
export function cartUpdated(event: CartEvent, cart: Order): Order {
	loaded.set(event.locals, Promise.resolve(cart));
	return cart;
}

function forget(event: CartEvent): void {
	clearCartToken(event.cookies);
	event.locals.cartToken = null;
	loaded.set(event.locals, Promise.resolve(null));
}

/**
 * Opens (or, for a logged-in customer, picks up) the cart and remembers its token.
 * Called at login so that the cart of a previous visit comes back.
 */
export async function attachCart(api: ShopClient, event: CartEvent): Promise<Order> {
	const cart = await api.post<Order>('/orders', {});
	setCartToken(event.cookies, event.url, cart.tokenValue);
	event.locals.cartToken = cart.tokenValue;
	return cartUpdated(event, cart);
}

/**
 * Cart of the session, `null` when there is none yet. A token that no longer designates a cart
 * (order placed, cart expired) is ignored for the request: the next addition opens a new cart and
 * replaces the cookie. The cookie is not deleted here: queries (header, cart page) may not write
 * cookies, only forms and commands may.
 */
export function currentCart(event: CartEvent): Promise<Order | null> {
	let cart = loaded.get(event.locals);
	if (!cart) {
		cart = (async () => {
			const token = event.locals.cartToken;
			if (!event.locals.viewer.canOrder || !token) return null;
			const order = await sylius(event).find<Order>(`/orders/${encodeURIComponent(token)}`);
			if (order?.state === 'cart') return order;
			event.locals.cartToken = null;
			return null;
		})();
		loaded.set(event.locals, cart);
	}
	return cart;
}

/** Header widget: item count and total of the cart, null when the viewer cannot order. */
export async function cartSummary(event: CartEvent): Promise<CartSummary | null> {
	if (!event.locals.viewer.canOrder) return null;
	const cart = await currentCart(event);
	return cart ? summary(cart) : EMPTY_CART;
}

/** Cart page content; `null` for an empty cart (or a viewer who cannot order). */
export interface CartView {
	lines: OrderLine[];
	totals: OrderTotals;
	escompte: YackOrder['escompte'];
	shipments: number;
}

export async function cartView(event: CartEvent): Promise<CartView | null> {
	if (!event.locals.viewer.canOrder) return null;
	const cart = await currentCart(event);
	if (!cart || cart.items.length === 0) return null;
	return { lines: orderLines(cart), totals: cart.yack.totals, escompte: cart.yack.escompte, shipments: cart.shipments.length };
}

/** Adds `quantity` of a variant to the cart (opened on first use); returns the new summary. */
export async function addToCart(event: CartEvent, variantCode: string, quantity: number): Promise<CartSummary> {
	const api = sylius(event);
	const item = { productVariant: `/api/v2/shop/product-variants/${variantCode}`, quantity };
	const token = (await currentCart(event))?.tokenValue ?? (await attachCart(api, event)).tokenValue;

	try {
		return summary(cartUpdated(event, await api.post<Order>(`/orders/${token}/items`, item)));
	} catch (error) {
		if (!(error instanceof ApiError) || error.status !== 404) throw error;
		// Cart gone in the meantime: start a new one once.
		const cart = await attachCart(api, event);
		return summary(cartUpdated(event, await api.post<Order>(`/orders/${cart.tokenValue}/items`, item)));
	}
}

/**
 * Applies new quantities to the lines of the cart (0 removes the line); unchanged lines are skipped.
 * Returns the number of lines changed.
 */
export async function updateCartItems(event: CartEvent, cart: Order, quantities: Map<number, number>): Promise<number> {
	const api = sylius(event);
	let changed = 0;
	for (const item of cart.items) {
		const quantity = quantities.get(item.id);
		if (quantity === undefined || quantity === item.quantity) continue;
		const path = `/orders/${cart.tokenValue}/items/${item.id}`;
		if (quantity > 0) cartUpdated(event, await api.patch<Order>(path, { quantity }));
		else await api.delete(path);
		changed++;
	}
	// A removal answers 204: the next read of the request reloads the cart.
	if (changed > 0) loaded.delete(event.locals);
	return changed;
}

/** The order is placed: the cookie must not designate it any more. */
export function cartCheckedOut(event: CartEvent): void {
	forget(event);
}
