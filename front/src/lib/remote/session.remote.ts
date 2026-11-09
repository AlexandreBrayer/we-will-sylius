// Customer session: who is browsing, login / logout (cookies yack_session / yack_cart, #lib/server/session.ts).
import { invalid, redirect } from '@sveltejs/kit';
import { form, getRequestEvent, query } from '$app/server';
import * as v from 'valibot';
import { attachCart } from '#lib/server/cart.js';
import { endSession, safeRedirect, startSession } from '#lib/server/session.js';
import { ApiError, customerToken, jwtExpiry, shopClient } from '#lib/server/sylius.js';
import type { Viewer } from '#lib/types.js';

/**
 * Who is browsing (profile, canSeePrices, canOrder…), loaded once per request by hooks.server.ts
 * (GET /api/v2/shop/yack/me). Every component needing it awaits this query (one cache entry).
 */
export const getViewer = query((): Viewer => getRequestEvent().locals.viewer);

/**
 * Login (/connexion): shop JWT (POST /api/v2/shop/customers/token) kept in the httpOnly cookie
 * yack_session, ECOM cart of a previous visit picked up, then back to `?redirect=`.
 * `_password` starts with "_": SvelteKit never sends it back to the page.
 */
export const login = form(
	v.object({
		email: v.pipe(v.string(), v.trim(), v.nonEmpty('Saisissez votre identifiant.')),
		_password: v.pipe(v.string(), v.nonEmpty('Saisissez votre mot de passe.')),
		remember: v.optional(v.boolean(), false)
	}),
	async ({ email, _password, remember }) => {
		const event = getRequestEvent();

		let token: string;
		try {
			token = await customerToken(email, _password);
		} catch (error) {
			if (!(error instanceof ApiError) || error.status >= 500) throw error;
			// Sylius répond « Identifiants invalides » aussi pour un compte pas encore activé (il ne dit pas si le compte
			// existe) : le message rappelle l'activation à qui vient de s'inscrire.
			invalid(
				/disabled|désactivé/i.test(error.message)
					? "Compte non activé : utilisez le lien d'activation reçu par e-mail."
					: "Identifiant ou mot de passe incorrect. Compte créé récemment : activez-le d'abord avec le lien reçu par e-mail."
			);
		}
		startSession(event.cookies, event.url, token, jwtExpiry(token), remember);

		// ECOM: pick up the cart of a previous visit (Sylius keeps one cart per customer).
		const api = shopClient(token);
		const viewer = await api.get<Viewer>('/yack/me');
		event.locals.sessionToken = token;
		event.locals.viewer = viewer;
		if (viewer.canOrder) await attachCart(api, event);

		// No explicit refresh: the client follows the redirect and refreshes every query (viewer, cart…).
		redirect(303, safeRedirect(event.url.searchParams.get('redirect')));
	}
);

/** Logout (header): the shop JWT is stateless, dropping the cookies ends the session. */
export const logout = form(async () => {
	endSession(getRequestEvent().cookies);
	redirect(303, '/');
});
