import { customerOrders, requireCustomer } from '#lib/server/account.js';
import { sylius } from '#lib/server/sylius.js';
import type { PageServerLoad } from './$types';

const PER_PAGE = 10;

/** Order history (ECOM). A PRO account has no orders: the page explains how to get the access. */
export const load: PageServerLoad = async (event) => {
	requireCustomer(event);
	if (!event.locals.viewer.canOrder) return { orders: null, page: 1, pages: 1, total: 0 };

	const orders = await customerOrders(sylius(event));
	const pages = Math.max(1, Math.ceil(orders.length / PER_PAGE));
	const page = Math.min(Math.max(1, Number(event.url.searchParams.get('page')) || 1), pages);
	return { orders: orders.slice((page - 1) * PER_PAGE, page * PER_PAGE), page, pages, total: orders.length };
};
