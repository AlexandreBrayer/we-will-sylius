import { redirect, type Handle } from '@sveltejs/kit';
import { TOKEN_COOKIE, loginUrl } from '$lib/server/auth.js';

/**
 * Builder protection: every /admin/* route (except the login form) needs the JWT of a Sylius
 * administrator. The JWT itself is checked by Sylius on each admin API call.
 */
export const handle: Handle = async ({ event, resolve }) => {
	event.locals.adminToken = event.cookies.get(TOKEN_COOKIE) ?? null;

	const { pathname } = event.url;
	if (pathname.startsWith('/admin') && pathname !== '/admin/login' && !event.locals.adminToken) {
		if (event.request.method !== 'GET') {
			return new Response(JSON.stringify({ message: 'Session expirée' }), { status: 401 });
		}
		redirect(303, loginUrl(event.url));
	}

	return resolve(event);
};
