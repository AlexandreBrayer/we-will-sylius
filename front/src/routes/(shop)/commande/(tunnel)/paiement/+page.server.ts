import { error, fail, redirect, type RequestEvent } from '@sveltejs/kit';
import { iriCode, type PaymentMethodChoice } from '#lib/order/types.js';
import { apiFailure, checkoutCart } from '#lib/server/checkout.js';
import { sylius } from '#lib/server/sylius.js';
import type { Actions, PageServerLoad } from './$types';

export const load: PageServerLoad = async (event) => {
	const { order } = await event.parent();
	const payment = order.payments[0];
	if (!payment) redirect(303, '/commande/recapitulatif');

	const methods = await sylius(event).get<PaymentMethodChoice[]>(`/orders/${order.tokenValue}/payments/${payment.id}/methods`);
	// Sylius pre-selects the first method (Virement sous 15 jours) and applies its escompte as soon
	// as the delivery is chosen: the summary already shows it on arrival.
	return { methods, selected: iriCode(payment.method) ?? methods[0]?.code ?? null };
};

/** PATCH /shop/orders/{token}/payments/{id}: recomputes escompte and VAT. */
async function choosePaymentMethod(event: RequestEvent) {
	const order = await checkoutCart(event);
	const payment = order.payments[0];
	if (!payment) error(409, 'Aucun paiement à choisir pour cette commande.');

	const method = String((await event.request.formData()).get('paymentMethod') ?? '');
	if (!method) return fail(400, { error: 'Choisissez un mode de paiement.' });

	try {
		await sylius(event).patch(`/orders/${order.tokenValue}/payments/${payment.id}`, { paymentMethod: method });
	} catch (e) {
		return apiFailure(e);
	}
	return null;
}

export const actions: Actions = {
	/** Method changed (JS): stay on the step, the summary shows the new escompte / VAT / total. */
	select: async (event) => (await choosePaymentMethod(event)) ?? { selected: true },

	/** "Suivant" (checkoutState → payment_selected). */
	next: async (event) => {
		const failure = await choosePaymentMethod(event);
		if (failure) return failure;
		redirect(303, '/commande/recapitulatif');
	}
};
