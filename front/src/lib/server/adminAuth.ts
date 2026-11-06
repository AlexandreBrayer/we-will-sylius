// Builder (/cms-admin) authentication: JWT of a Sylius administrator in an httpOnly cookie
// (set by the `builderLogin` remote form, dropped by /cms-admin/logout).

export const TOKEN_COOKIE = 'yack_cms_token';

export function adminLoginUrl(url: URL): string {
	return `/cms-admin/login?redirect=${encodeURIComponent(url.pathname + url.search)}`;
}
