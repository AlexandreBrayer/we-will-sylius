import { fail, redirect } from '@sveltejs/kit';
import { cartCheckedOut } from '#lib/server/cart.js';
import { apiFailure, checkoutCart, guardStep } from '#lib/server/checkout.js';
import { sylius } from '#lib/server/sylius.js';
import type { Actions } from './$types';

export const actions: Actions = {
	/** PATCH /shop/orders/{token}/complete: the order is placed, confirmation e-mail sent by Sylius. */
	default: async (event) => {
		const order = await checkoutCart(event);
		guardStep(order, 'complete');

		const form = await event.request.formData();
		const notes = String(form.get('notes') ?? '').trim();
		if (form.get('cgv') !== '1') {
			return fail(400, { error: 'Acceptez les conditions générales de vente pour valider la commande.', notes });
		}

		try {
			await sylius(event).patch(`/orders/${order.tokenValue}/complete`, { notes: notes || null });
		} catch (e) {
			const failure = apiFailure(e);
			return fail(failure.status, { ...failure.data, notes });
		}
		cartCheckedOut(event);
		redirect(303, `/commande/confirmation/${order.tokenValue}`);
	}
};
