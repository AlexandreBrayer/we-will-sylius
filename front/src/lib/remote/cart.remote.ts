// ECOM cart (#lib/server/cart.ts). Mutations update `getCartSummary` (header widget) in the same
// response: `getCartSummary().set(…)` / `.refresh()` server-side (single-flight mutation).
import { invalid, redirect } from '@sveltejs/kit';
import { form, getRequestEvent, query } from '$app/server';
import * as v from 'valibot';
import { addToCart as addCartItem, cartSummary, cartView, currentCart, updateCartItems, type CartView } from '#lib/server/cart.js';
import { loginUrl, pageUrl } from '#lib/server/session.js';
import { ApiError } from '#lib/server/sylius.js';
import type { CartSummary } from '#lib/types.js';

/** Header cart widget: item count and total, null when the viewer cannot order (visitor, PRO). */
export const getCartSummary = query((): Promise<CartSummary | null> => cartSummary(getRequestEvent()));

/**
 * Cart page (/panier): lines, Yack totals; `null` for an empty cart or a PRO account (the page
 * explains why). Visitor → /connexion, back to the cart.
 */
export const getCart = query((): Promise<CartView | null> => {
	const event = getRequestEvent();
	if (!event.locals.viewer.authenticated) redirect(303, loginUrl(pageUrl(event)));
	return cartView(event);
});

const ORDER_ONLY = 'La commande en ligne est réservée aux comptes ECOM.';
const QUANTITY = 'Saisissez une quantité entière supérieure ou égale à 1.';
const LINE_QUANTITY = 'Quantité invalide : saisissez un nombre entier (0 retire la ligne).';

/**
 * "Ajouter au panier" (ECOM): POST /api/v2/shop/orders/{cart}/items, cart opened on first use.
 * Visitor → /connexion (back to the page); PRO → form issue. Fields: `variant` (hidden),
 * `quantity` (`fields.quantity.as('number')`). Result: `{ added }` (quantity added).
 * Several forms on a page (SAV parts list): `addToCart.for(variantCode)`.
 */
export const addToCart = form(
	v.object({
		variant: v.pipe(v.string(), v.nonEmpty('Produit indisponible à la commande.')),
		quantity: v.pipe(v.number(QUANTITY), v.integer(QUANTITY), v.minValue(1, QUANTITY), v.maxValue(9999, QUANTITY))
	}),
	async ({ variant, quantity }) => {
		const event = getRequestEvent();
		const { viewer } = event.locals;
		if (!viewer.authenticated) redirect(303, loginUrl(new URL(event.url.pathname, event.url)));
		if (!viewer.canOrder) invalid(ORDER_ONLY);

		let cart: CartSummary;
		try {
			cart = await addCartItem(event, variant, quantity);
		} catch (error) {
			if (error instanceof ApiError && error.status < 500) invalid(error.message);
			throw error;
		}
		getCartSummary().set(cart);

		return { added: quantity };
	}
);

/**
 * Cart page form: `items[i].id` (hidden) + `items[i].quantity` (0 removes the line), saved by
 * "Mettre à jour le panier" (or on change with JS); a `remove` submit button (value = line id)
 * removes one line and ignores the other edits. Result: `{ updated, removed }` (name of the removed line).
 */
export const updateCart = form(
	v.object({
		items: v.optional(
			v.array(
				v.object({
					id: v.string(),
					quantity: v.pipe(v.number(LINE_QUANTITY), v.integer(LINE_QUANTITY), v.minValue(0, LINE_QUANTITY), v.maxValue(9999, LINE_QUANTITY))
				})
			),
			[]
		),
		remove: v.optional(v.string())
	}),
	async ({ items, remove }) => {
		const event = getRequestEvent();
		if (!event.locals.viewer.canOrder) invalid(ORDER_ONLY);
		const cart = await currentCart(event);
		if (!cart) return { updated: 0, removed: null };

		const removed = remove ? (cart.items.find((item) => String(item.id) === remove) ?? null) : null;
		const quantities = removed ? new Map([[removed.id, 0]]) : new Map(items.map((item) => [Number(item.id), item.quantity]));

		let updated: number;
		try {
			updated = await updateCartItems(event, cart, quantities);
		} catch (error) {
			if (error instanceof ApiError && error.status < 500) invalid(error.message);
			throw error;
		}
		await Promise.all([getCart().refresh(), getCartSummary().refresh()]);

		return { updated, removed: removed?.productName ?? null };
	}
);
