// Customer account (/inscription, /compte/…): guard and API helpers behind #lib/remote/account.remote.ts
// and #lib/remote/registration.remote.ts.
import { redirect } from '@sveltejs/kit';
import type { StandardSchemaV1 } from '@standard-schema/spec';
import type { OrderSummary } from '#lib/account/types.js';
import type { Viewer } from '#lib/types.js';
import { loginUrl, pageUrl } from './session.js';
import type { ShopClient, ShopEvent, Violation } from './sylius.js';

/** Short-lived cookie carrying `{email, pro}` from the sign-up to the "check your e-mails" page. */
export const REGISTERED_COOKIE = 'yack_registered';

export type CustomerViewer = Viewer & { authenticated: true; customerId: number };

/** Customer of the session; anonymous visitors are sent to /connexion, coming back to the page afterwards. */
export function requireCustomer(event: Pick<ShopEvent, 'locals' | 'request' | 'isRemoteRequest'>): CustomerViewer {
	const { viewer } = event.locals;
	if (!viewer.authenticated || viewer.customerId === null) redirect(303, loginUrl(pageUrl(event)));
	return viewer as CustomerViewer;
}

/**
 * Violations of an API 422 as form issues for `invalid(…)`: `fields` maps an API property path to the
 * form field (first message of each field); other violations become issues of the whole form.
 */
export function violationIssues(violations: Violation[], fields: Record<string, string>): StandardSchemaV1.Issue[] {
	const seen = new Set<string>();
	const issues: StandardSchemaV1.Issue[] = [];
	for (const { propertyPath, message } of violations) {
		const field = fields[propertyPath];
		if (field === undefined) {
			issues.push({ message });
		} else if (!seen.has(field)) {
			seen.add(field);
			issues.push({ message, path: [field] });
		}
	}
	return issues;
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
