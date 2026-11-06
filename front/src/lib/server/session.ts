// Customer session of the shop: two httpOnly cookies, never readable by the browser's JS.
//  - yack_session: shop JWT of the customer (POST /api/v2/shop/customers/token);
//  - yack_cart: tokenValue of the cart (Sylius order in state "cart"), ECOM accounts only.
import type { Cookies, RequestEvent } from '@sveltejs/kit';

export const SESSION_COOKIE = 'yack_session';
export const CART_COOKIE = 'yack_cart';

/** Cart cookie lifetime: the Sylius cart outlives the browser session. */
const CART_MAX_AGE = 60 * 60 * 24 * 30;

const options = (url: URL) => ({ path: '/', httpOnly: true, sameSite: 'lax' as const, secure: url.protocol === 'https:' });

/**
 * Opens the session. `remember` ("Rester connecté") keeps the cookie until the JWT expires;
 * otherwise it is a browser-session cookie.
 */
export function startSession(cookies: Cookies, url: URL, token: string, expiresAt: number | null, remember: boolean): void {
	const maxAge = remember && expiresAt ? Math.max(0, expiresAt - Math.floor(Date.now() / 1000)) : undefined;
	cookies.set(SESSION_COOKIE, token, { ...options(url), maxAge });
}

export function endSession(cookies: Cookies): void {
	cookies.delete(SESSION_COOKIE, { path: '/' });
	cookies.delete(CART_COOKIE, { path: '/' });
}

export function setCartToken(cookies: Cookies, url: URL, tokenValue: string): void {
	cookies.set(CART_COOKIE, tokenValue, { ...options(url), maxAge: CART_MAX_AGE });
}

export function clearCartToken(cookies: Cookies): void {
	cookies.delete(CART_COOKIE, { path: '/' });
}

/** Login form URL, coming back to the current page afterwards. */
export function loginUrl(url: URL, { expired = false } = {}): string {
	const params = new URLSearchParams({ redirect: url.pathname + url.search });
	if (expired) params.set('expire', '1');
	return `/connexion?${params}`;
}

/**
 * URL of the page the request comes from. Remote forms and commands carry it (SvelteKit sets
 * `event.url` from the x-sveltekit-pathname / -search headers); a remote query called from the
 * browser keeps its endpoint URL (/_app/remote/…): the page is then the same-origin Referer.
 */
export function pageUrl(event: Pick<RequestEvent, 'url' | 'request' | 'isRemoteRequest'>): URL {
	if (!event.isRemoteRequest || event.request.headers.has('x-sveltekit-pathname')) return event.url;
	const referer = event.request.headers.get('referer');
	const url = referer ? URL.parse(referer) : null;
	return url && url.origin === event.url.origin ? url : new URL('/', event.url);
}

/** Local redirect target (open-redirect safe), `fallback` otherwise. */
export function safeRedirect(target: string | null, fallback = '/'): string {
	return target && target.startsWith('/') && !target.startsWith('//') && !target.startsWith('/\\') ? target : fallback;
}
