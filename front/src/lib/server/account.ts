// Customer account (/compte/…): guard and API helpers shared by the account pages.
import { redirect, type RequestEvent } from '@sveltejs/kit';
import type { OrderSummary } from '#lib/account/types.js';
import type { OrderAddress } from '#lib/order/types.js';
import { loginUrl } from './session.js';
import type { ShopClient, Violation } from './sylius.js';

/** Short-lived cookie (path /inscription) carrying `{email, pro}` to the "check your e-mails" page. */
export const REGISTERED_COOKIE = 'yack_registered';

/** Anonymous visitors are sent to /connexion, coming back here afterwards. */
export function requireCustomer(event: Pick<RequestEvent, 'locals' | 'url'>): void {
	if (!event.locals.viewer.authenticated) redirect(303, loginUrl(event.url));
}

/** Violations of an API 422 by property path (first message of each field). */
export function fieldErrors(violations: Violation[]): Record<string, string> {
	const errors: Record<string, string> = {};
	for (const { propertyPath, message } of violations) errors[propertyPath] ??= message;
	return errors;
}

/** Address book form → body of POST / PUT /shop/addresses (blank optional fields sent as null). */
export function readAddress(form: FormData): OrderAddress {
	const text = (name: string) => String(form.get(name) ?? '').trim();
	return {
		firstName: text('firstName'),
		lastName: text('lastName'),
		company: text('company') || null,
		street: text('street'),
		postcode: text('postcode'),
		city: text('city'),
		countryCode: text('countryCode'),
		phoneNumber: text('phoneNumber') || null
	};
}

/** Placed orders of the customer, most recent first (the API lists them oldest first, without sort filter). */
export async function customerOrders(api: ShopClient): Promise<OrderSummary[]> {
	const orders: OrderSummary[] = [];
	for (let page = 1; ; page++) {
		const { items, total } = await api.collection<OrderSummary>('/orders', { page, itemsPerPage: 100 });
		orders.push(...items);
		if (items.length === 0 || orders.length >= total) break;
	}
	return orders
		.filter((order) => order.state !== 'cart' && order.number && order.checkoutCompletedAt)
		.sort((a, b) => (b.checkoutCompletedAt ?? '').localeCompare(a.checkoutCompletedAt ?? ''));
}
