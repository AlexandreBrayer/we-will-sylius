// Builder (/cms-admin) authentication: JWT of a Sylius administrator in an httpOnly cookie.
import { redirect, type Cookies } from '@sveltejs/kit';
import { AdminAuthError } from './sylius.js';

export const TOKEN_COOKIE = 'yack_cms_token';

export function adminLoginUrl(url: URL): string {
	return `/cms-admin/login?redirect=${encodeURIComponent(url.pathname + url.search)}`;
}

/** Expired / revoked JWT: drop the cookie and go back to the login form. */
export function handleAuthError(error: unknown, cookies: Cookies, url: URL): never {
	if (error instanceof AdminAuthError) {
		cookies.delete(TOKEN_COOKIE, { path: '/' });
		redirect(303, adminLoginUrl(url));
	}
	throw error;
}
