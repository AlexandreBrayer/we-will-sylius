// ECOM cart (Sylius order in state "cart"), identified by its tokenValue in the yack_cart cookie.
// The Sylius API reserves carts to ECOM accounts (visitor 401, PRO 403).
import type { RequestEvent } from '@sveltejs/kit';
import type { CartSummary } from '#lib/types.js';
import { clearCartToken, setCartToken } from './session.js';
import { ApiError, sylius, type ShopClient } from './sylius.js';

type CartEvent = Pick<RequestEvent, 'locals' | 'cookies' | 'url'>;

interface ApiCart {
	tokenValue: string;
	itemsTotal: number;
	items: { quantity: number }[];
}

const EMPTY: CartSummary = { itemsCount: 0, itemsTotal: 0 };

const summary = (cart: ApiCart): CartSummary => ({
	itemsCount: cart.items.reduce((count, item) => count + item.quantity, 0),
	itemsTotal: cart.itemsTotal
});

/**
 * Opens (or, for a logged-in customer, picks up) the cart and remembers its token.
 * Called at login so that the cart of a previous visit comes back.
 */
export async function attachCart(api: ShopClient, event: CartEvent): Promise<ApiCart> {
	const cart = await api.post<ApiCart>('/orders', {});
	setCartToken(event.cookies, event.url, cart.tokenValue);
	event.locals.cartToken = cart.tokenValue;
	return cart;
}

/** Header widget: item count and total of the cart, null when the viewer cannot order. */
export async function cartSummary(event: CartEvent): Promise<CartSummary | null> {
	if (!event.locals.viewer.canOrder) return null;
	if (!event.locals.cartToken) return EMPTY;

	const cart = await sylius(event).find<ApiCart>(`/orders/${event.locals.cartToken}`);
	if (cart) return summary(cart);
	// Cart gone (checked out, expired): the next addition opens a new one.
	clearCartToken(event.cookies);
	event.locals.cartToken = null;
	return EMPTY;
}

/** Adds `quantity` of a variant to the cart (opened on first use); returns the new summary. */
export async function addToCart(event: CartEvent, variantCode: string, quantity: number): Promise<CartSummary> {
	const api = sylius(event);
	const item = { productVariant: `/api/v2/shop/product-variants/${variantCode}`, quantity };
	const token = event.locals.cartToken ?? (await attachCart(api, event)).tokenValue;

	try {
		return summary(await api.post<ApiCart>(`/orders/${token}/items`, item));
	} catch (error) {
		if (!(error instanceof ApiError) || error.status !== 404) throw error;
		// Stale cookie: start a new cart once.
		const cart = await attachCart(api, event);
		return summary(await api.post<ApiCart>(`/orders/${cart.tokenValue}/items`, item));
	}
}
