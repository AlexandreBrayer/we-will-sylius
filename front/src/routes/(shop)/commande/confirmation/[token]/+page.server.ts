import { error, redirect } from '@sveltejs/kit';
import { orderLines, type Order } from '#lib/order/types.js';
import { loginUrl } from '#lib/server/session.js';
import { sylius } from '#lib/server/sylius.js';
import type { PageServerLoad } from './$types';

/** "Merci pour votre commande" (Sylius order/thank_you): number, payment terms, summary. */
export const load: PageServerLoad = async (event) => {
	const { viewer } = event.locals;
	if (!viewer.authenticated) redirect(303, loginUrl(event.url));
	if (!viewer.canOrder) error(404, 'Commande introuvable');

	const order = await sylius(event).find<Order>(`/orders/${encodeURIComponent(event.params.token)}`);
	if (!order || order.checkoutState !== 'completed') error(404, 'Commande introuvable');

	return { order, lines: orderLines(order) };
};
