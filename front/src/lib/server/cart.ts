// ECOM cart (Sylius order in state "cart"), identified by its tokenValue in the yack_cart cookie.
// The Sylius API reserves carts to ECOM accounts (visitor 401, PRO 403). There is no visitor cart
// to merge at login: the customer gets back the latest non-empty cart Sylius kept for them
// (POST /shop/orders picks it up), otherwise a new one.
import type { Order } from '#lib/order/types.js';
import type { CartSummary } from '#lib/types.js';
import { clearCartToken, setCartToken } from './session.js';
import { ApiError, sylius, type ShopClient, type ShopEvent } from './sylius.js';

type CartEvent = ShopEvent;

const EMPTY: CartSummary = { itemsCount: 0, itemsTotal: 0 };

const summary = (cart: Order): CartSummary => ({
	itemsCount: cart.items.reduce((count, item) => count + item.quantity, 0),
	itemsTotal: cart.itemsTotal
});

/** One cart load per request: the root layout (header) and the page share it. */
const loaded = new WeakMap<App.Locals, Promise<Order | null>>();

function remember(event: CartEvent, cart: Order): Order {
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
	return remember(event, cart);
}

/**
 * Cart of the session, `null` when there is none yet. A token that no longer designates a cart
 * (order placed, cart expired) is dropped: the next addition opens a new cart.
 */
export function currentCart(event: CartEvent): Promise<Order | null> {
	let cart = loaded.get(event.locals);
	if (!cart) {
		cart = (async () => {
			const token = event.locals.cartToken;
			if (!event.locals.viewer.canOrder || !token) return null;
			const order = await sylius(event).find<Order>(`/orders/${encodeURIComponent(token)}`);
			if (order?.state === 'cart') return order;
			forget(event);
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
	return cart ? summary(cart) : EMPTY;
}

/** Adds `quantity` of a variant to the cart (opened on first use); returns the new summary. */
export async function addToCart(event: CartEvent, variantCode: string, quantity: number): Promise<CartSummary> {
	const api = sylius(event);
	const item = { productVariant: `/api/v2/shop/product-variants/${variantCode}`, quantity };
	const token = (await currentCart(event))?.tokenValue ?? (await attachCart(api, event)).tokenValue;

	try {
		return summary(remember(event, await api.post<Order>(`/orders/${token}/items`, item)));
	} catch (error) {
		if (!(error instanceof ApiError) || error.status !== 404) throw error;
		// Cart gone in the meantime: start a new one once.
		const cart = await attachCart(api, event);
		return summary(remember(event, await api.post<Order>(`/orders/${cart.tokenValue}/items`, item)));
	}
}

/** Sets the quantity of a cart line (0 removes it). */
export async function setItemQuantity(event: CartEvent, cart: Order, itemId: number, quantity: number): Promise<void> {
	const path = `/orders/${cart.tokenValue}/items/${itemId}`;
	if (quantity > 0) remember(event, await sylius(event).patch<Order>(path, { quantity }));
	else await removeItem(event, cart, itemId);
}

export async function removeItem(event: CartEvent, cart: Order, itemId: number): Promise<void> {
	await sylius(event).delete(`/orders/${cart.tokenValue}/items/${itemId}`);
	loaded.delete(event.locals);
}

/** The order is placed: the cookie must not designate it any more. */
export function cartCheckedOut(event: CartEvent): void {
	forget(event);
}
