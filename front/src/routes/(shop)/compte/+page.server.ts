import type { Customer } from '#lib/account/types.js';
import { customerOrders, requireCustomer } from '#lib/server/account.js';
import { sylius } from '#lib/server/sylius.js';
import type { PageServerLoad } from './$types';

/** Dashboard: identity, Yack profile and company, order access (PRO) or latest orders (ECOM). */
export const load: PageServerLoad = async (event) => {
	requireCustomer(event);
	const { viewer } = event.locals;
	const api = sylius(event);
	const [customer, orders] = await Promise.all([
		api.get<Customer>(`/customers/${viewer.customerId}`),
		viewer.canOrder ? customerOrders(api) : Promise.resolve(null)
	]);
	return { customer, recentOrders: orders?.slice(0, 3) ?? null, orderCount: orders?.length ?? 0 };
};
