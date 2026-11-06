import { fail, redirect } from '@sveltejs/kit';
import { attachCart } from '#lib/server/cart.js';
import { safeRedirect, startSession } from '#lib/server/session.js';
import { ApiError, customerToken, jwtExpiry, shopClient } from '#lib/server/sylius.js';
import type { Viewer } from '#lib/types.js';
import type { Actions, PageServerLoad } from './$types';

export const load: PageServerLoad = ({ locals, url }) => {
	if (locals.viewer.authenticated) redirect(303, safeRedirect(url.searchParams.get('redirect')));
	return { expired: url.searchParams.has('expire') };
};

export const actions: Actions = {
	/** Shop JWT (POST /api/v2/shop/customers/token) kept in the httpOnly cookie yack_session. */
	default: async (event) => {
		const { request, cookies, url } = event;
		const form = await request.formData();
		const email = String(form.get('email') ?? '').trim();
		const password = String(form.get('password') ?? '');
		const remember = form.get('remember') === '1';
		if (!email || !password) return fail(400, { email, error: 'Saisissez votre identifiant et votre mot de passe.' });

		let token: string;
		try {
			token = await customerToken(email, password);
		} catch (error) {
			if (!(error instanceof ApiError) || error.status >= 500) throw error;
			const disabled = /disabled|désactivé/i.test(error.message);
			return fail(400, {
				email,
				error: disabled
					? "Compte non activé : utilisez le lien d'activation reçu par e-mail."
					: 'Identifiant ou mot de passe incorrect.'
			});
		}
		startSession(cookies, url, token, jwtExpiry(token), remember);

		// ECOM: pick up the cart of a previous visit (Sylius keeps one cart per customer).
		const api = shopClient(token);
		const viewer = await api.get<Viewer>('/yack/me');
		event.locals.viewer = viewer;
		if (viewer.canOrder) await attachCart(api, event);

		redirect(303, safeRedirect(url.searchParams.get('redirect')));
	}
};
