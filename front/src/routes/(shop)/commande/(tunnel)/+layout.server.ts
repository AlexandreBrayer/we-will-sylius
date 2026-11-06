import { routeStep } from '#lib/order/checkout.js';
import { orderLines } from '#lib/order/types.js';
import { checkoutCart, guardStep } from '#lib/server/checkout.js';
import type { LayoutServerLoad } from './$types';

/** Checkout steps (ECOM, non-empty cart): cart being ordered, its lines and the current step. */
export const load: LayoutServerLoad = async (event) => {
	const order = await checkoutCart(event);
	const step = routeStep(event.route.id);
	guardStep(order, step);

	return { order, lines: orderLines(order), step };
};
