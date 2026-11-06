import { fail, redirect } from '@sveltejs/kit';
import type { OrderAccessRequestDraft, OrderAccessRequestView } from '#lib/account/types.js';
import { fieldErrors, requireCustomer } from '#lib/server/account.js';
import { ApiError, sylius } from '#lib/server/sylius.js';
import type { Actions, PageServerLoad } from './$types';

/** PRO → ECOM "commander en ligne" request (port of templates/shop/yack/account/order_access_request.html.twig). */
export const load: PageServerLoad = async (event) => {
	requireCustomer(event);
	const { viewer } = event.locals;
	const sent = event.url.searchParams.get('envoyee') === '1';
	if (!viewer.canRequestOrderAccess) return { canRequest: false, draft: null, latest: null, sent };

	const api = sylius(event);
	const [draft, requests] = await Promise.all([
		api.get<OrderAccessRequestDraft>('/yack/order-access-requests/draft'),
		api.get<OrderAccessRequestView[]>('/yack/order-access-requests')
	]);
	return { canRequest: true, draft, latest: requests[0] ?? null, sent };
};

const FIELDS = ['company', 'siret', 'fullName', 'email', 'phone', 'address', 'message'] as const;

export const actions: Actions = {
	/** POST /shop/yack/order-access-requests (201; 409 when one is already pending; 422). */
	default: async (event) => {
		requireCustomer(event);
		const form = await event.request.formData();
		const values = Object.fromEntries(FIELDS.map((field) => [field, String(form.get(field) ?? '').trim()])) as Record<(typeof FIELDS)[number], string>;

		try {
			await sylius(event).post('/yack/order-access-requests', { ...values, message: values.message || null });
		} catch (error) {
			if (!(error instanceof ApiError) || error.status >= 500) throw error;
			if (error.status === 422) return fail(422, { values, errors: fieldErrors(error.violations), error: null });
			return fail(error.status, { values, errors: {}, error: error.message });
		}
		redirect(303, '/compte/acces-commande?envoyee=1');
	}
};
