import { error, fail, redirect } from '@sveltejs/kit';
import { iriCode, type ShippingMethodChoice } from '#lib/order/types.js';
import { apiFailure, checkoutCart } from '#lib/server/checkout.js';
import { sylius } from '#lib/server/sylius.js';
import type { Actions, PageServerLoad } from './$types';

export const load: PageServerLoad = async (event) => {
	const { order } = await event.parent();
	const shipment = order.shipments[0];
	if (!shipment) redirect(303, '/commande/paiement');

	const methods = await sylius(event).get<ShippingMethodChoice[]>(`/orders/${order.tokenValue}/shipments/${shipment.id}/methods`);
	return { methods, selected: iriCode(shipment.method) ?? methods[0]?.code ?? null };
};

export const actions: Actions = {
	/** PATCH /shop/orders/{token}/shipments/{id} (checkoutState → shipping_selected). */
	default: async (event) => {
		const order = await checkoutCart(event);
		const shipment = order.shipments[0];
		if (!shipment) error(409, 'Aucune livraison à choisir pour cette commande.');

		const method = String((await event.request.formData()).get('shippingMethod') ?? '');
		if (!method) return fail(400, { error: 'Choisissez un mode de livraison.' });

		try {
			await sylius(event).patch(`/orders/${order.tokenValue}/shipments/${shipment.id}`, { shippingMethod: method });
		} catch (e) {
			return apiFailure(e);
		}
		redirect(303, '/commande/paiement');
	}
};
