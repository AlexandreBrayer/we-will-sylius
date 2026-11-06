import { fail, redirect, type RequestEvent } from '@sveltejs/kit';
import { currentCart, removeItem, setItemQuantity } from '#lib/server/cart.js';
import { apiFailure } from '#lib/server/checkout.js';
import { loginUrl } from '#lib/server/session.js';
import { orderLines } from '#lib/order/types.js';
import type { Actions, PageServerLoad } from './$types';

/** Cart page: ECOM only. A visitor logs in first; a PRO account gets the explanation (no API call). */
export const load: PageServerLoad = async (event) => {
	const { viewer } = event.locals;
	if (!viewer.authenticated) redirect(303, loginUrl(event.url));
	if (!viewer.canOrder) return { cart: null };

	const cart = await currentCart(event);
	return {
		cart: cart && cart.items.length > 0 ? { lines: orderLines(cart), totals: cart.yack.totals, escompte: cart.yack.escompte, shipments: cart.shipments.length } : null
	};
};

/** Cart of the request for a mutation; `null` = nothing to change (no cart, not ECOM). */
async function editableCart(event: RequestEvent) {
	if (!event.locals.viewer.canOrder) return null;
	return currentCart(event);
}

const MAX_QUANTITY = 9999;

export const actions: Actions = {
	/** "Mettre à jour le panier": quantity-<itemId> fields; 0 removes the line. */
	update: async (event) => {
		const cart = await editableCart(event);
		if (!cart) return fail(403, { error: 'La commande en ligne est réservée aux comptes ECOM.' });

		const form = await event.request.formData();
		const changes: { id: number; quantity: number }[] = [];
		for (const item of cart.items) {
			const raw = form.get(`quantity-${item.id}`);
			if (raw === null) continue;
			const quantity = Number(raw);
			if (!Number.isInteger(quantity) || quantity < 0 || quantity > MAX_QUANTITY) {
				return fail(400, { error: `Quantité invalide pour « ${item.productName} » : saisissez un nombre entier.` });
			}
			if (quantity !== item.quantity) changes.push({ id: item.id, quantity });
		}

		try {
			for (const change of changes) await setItemQuantity(event, cart, change.id, change.quantity);
		} catch (e) {
			return apiFailure(e);
		}
		return { updated: changes.length > 0 };
	},

	/** Removes one line (button name="item" value=<itemId>). */
	remove: async (event) => {
		const cart = await editableCart(event);
		if (!cart) return fail(403, { error: 'La commande en ligne est réservée aux comptes ECOM.' });

		const id = Number((await event.request.formData()).get('item'));
		const item = cart.items.find((i) => i.id === id);
		if (!item) return { removed: null };

		try {
			await removeItem(event, cart, item.id);
		} catch (e) {
			return apiFailure(e);
		}
		return { removed: item.productName };
	}
};
