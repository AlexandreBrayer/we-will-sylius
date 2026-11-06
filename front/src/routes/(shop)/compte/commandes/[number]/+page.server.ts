import { error } from '@sveltejs/kit';
import { customerOrders, requireCustomer } from '#lib/server/account.js';
import { sylius } from '#lib/server/sylius.js';
import type { Order } from '#lib/order/types.js';
import type { PageServerLoad } from './$types';

/**
 * Order detail (link of the confirmation / shipment e-mails). The API loads an order by tokenValue:
 * it is found in the customer's history from the number.
 */
export const load: PageServerLoad = async (event) => {
	requireCustomer(event);
	if (!event.locals.viewer.canOrder) return { order: null };

	const api = sylius(event);
	const summary = (await customerOrders(api)).find((order) => order.number === event.params.number);
	if (!summary) error(404, 'Commande introuvable');
	return { order: await api.get<Order>(`/orders/${encodeURIComponent(summary.tokenValue)}`) };
};
