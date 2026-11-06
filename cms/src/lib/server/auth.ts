import { redirect, type Cookies } from '@sveltejs/kit';
import { AdminAuthError } from './sylius.js';

export const TOKEN_COOKIE = 'yack_cms_token';

export function loginUrl(url: URL): string {
	return `/admin/login?redirect=${encodeURIComponent(url.pathname + url.search)}`;
}

/** Expired / revoked JWT: drop the cookie and go back to the login form. */
export function handleAuthError(error: unknown, cookies: Cookies, url: URL): never {
	if (error instanceof AdminAuthError) {
		cookies.delete(TOKEN_COOKIE, { path: '/' });
		redirect(303, loginUrl(url));
	}
	throw error;
}
