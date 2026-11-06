// ECOM cart (#lib/server/cart.ts). Mutations update `getCartSummary` (header widget) in the same
// response: `getCartSummary().set(…)` / `.refresh()` server-side (single-flight mutation).
import { invalid, redirect } from '@sveltejs/kit';
import { form, getRequestEvent, query } from '$app/server';
import * as v from 'valibot';
import { addToCart as addCartItem, cartSummary } from '#lib/server/cart.js';
import { loginUrl } from '#lib/server/session.js';
import { ApiError } from '#lib/server/sylius.js';
import type { CartSummary } from '#lib/types.js';

/** Header cart widget: item count and total, null when the viewer cannot order (visitor, PRO). */
export const getCartSummary = query((): Promise<CartSummary | null> => cartSummary(getRequestEvent()));

const QUANTITY = 'Saisissez une quantité entière supérieure ou égale à 1.';

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
		if (!viewer.canOrder) invalid('La commande en ligne est réservée aux comptes ECOM.');

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
