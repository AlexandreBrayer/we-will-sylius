import { redirect } from '@sveltejs/kit';
import { sequence, type Handle, type HandleServerError } from '@sveltejs/kit/hooks';
import { TOKEN_COOKIE, adminLoginUrl } from '#lib/server/adminAuth.js';
import { CART_COOKIE, SESSION_COOKIE, endSession } from '#lib/server/session.js';
import { ApiError, shopClient } from '#lib/server/sylius.js';
import { VISITOR, type Viewer } from '#lib/types.js';

/**
 * Shop session: reads the cookies and loads who is browsing (GET /api/v2/shop/yack/me).
 * A JWT rejected by Sylius ends the session; the request goes on as a visitor.
 */
const session: Handle = async ({ event, resolve }) => {
	event.locals.sessionToken = event.cookies.get(SESSION_COOKIE) ?? null;
	event.locals.cartToken = event.cookies.get(CART_COOKIE) ?? null;
	event.locals.viewer = VISITOR;

	if (event.locals.sessionToken && !event.url.pathname.startsWith('/cms-admin')) {
		try {
			event.locals.viewer = await shopClient(event.locals.sessionToken).get<Viewer>('/yack/me');
		} catch (error) {
			if (!(error instanceof ApiError && error.status === 401)) throw error;
			endSession(event.cookies);
			event.locals.sessionToken = null;
			event.locals.cartToken = null;
		}
	}

	return resolve(event);
};

/**
 * Builder protection: every /cms-admin/* route (except the login form) needs the JWT of a
 * Sylius administrator. The JWT itself is checked by Sylius on each admin API call.
 */
const builder: Handle = async ({ event, resolve }) => {
	event.locals.adminToken = event.cookies.get(TOKEN_COOKIE) ?? null;

	const { pathname } = event.url;
	if (pathname.startsWith('/cms-admin') && pathname !== '/cms-admin/login' && !event.locals.adminToken) {
		if (event.request.method !== 'GET') {
			return new Response(JSON.stringify({ message: 'Session expirée' }), { status: 401 });
		}
		redirect(303, adminLoginUrl(event.url));
	}

	return resolve(event);
};

export const handle = sequence(session, builder);

/**
 * Every error goes through here (SvelteKit 3). App errors (`error(404, 'Produit introuvable')`) are
 * already French and safe; framework errors get a French message; unknown ones are logged and hidden.
 */
export const handleError: HandleServerError = ({ kind, error }) => {
	if (kind === 'unknown') {
		console.error(error);
		return { message: 'Une erreur est survenue' };
	}
	if (kind === 'framework' && error.status === 404) return { message: 'Page introuvable' };
};
