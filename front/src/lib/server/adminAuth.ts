// Builder (/cms-admin) authentication: JWT of a Sylius administrator in an httpOnly cookie, set by
// the back-office (/admin/yack/builder, BuilderAccessController) from the admin session: one login
// for the admin and the builder. /cms-admin/logout drops the cookie.

export const TOKEN_COOKIE = 'yack_cms_token';

/** Opens `url` of the builder through the admin session (its login form first when there is none). */
export function adminLoginUrl(url: URL): string {
	return `/admin/yack/builder?vers=${encodeURIComponent(url.pathname + url.search)}`;
}
