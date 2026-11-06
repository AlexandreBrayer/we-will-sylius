// Sylius API client (server side only).
//  - shop API (public): published CMS pages, products, taxons
//  - admin API: JWT of a Sylius administrator (ROLE_API_ACCESS), obtained by /admin/login
import type { Catalog, CmsPage, CmsPageSummary, ProductCard, TaxonCard } from '$lib/blocks/types.js';

const trim = (url: string) => url.replace(/\/+$/, '');

// Runtime environment (compose.yml); the public URL reaches the browser through load() data.
/** Sylius as reached by this server (Docker: http://nginx). */
export const syliusApiUrl = (): string => trim(process.env.SYLIUS_API_URL || 'http://localhost');

/** Sylius as reached by the browser (CSS, images, shop links). */
export const syliusPublicUrl = (): string => trim(process.env.PUBLIC_SYLIUS_URL || 'http://localhost');

const LOCALE = 'fr_FR';

export class AdminAuthError extends Error {}

export class ApiError extends Error {
	constructor(
		readonly status: number,
		message: string
	) {
		super(message);
	}
}

async function request(path: string, init: RequestInit = {}, token?: string | null): Promise<Response> {
	const headers = new Headers(init.headers);
	headers.set('Accept', 'application/json');
	headers.set('Accept-Language', LOCALE);
	if (init.body) headers.set('Content-Type', 'application/json');
	if (token) headers.set('Authorization', `Bearer ${token}`);

	return fetch(`${syliusApiUrl()}/api/v2${path}`, { ...init, headers });
}

/** French message of an API Platform error (violations joined). */
async function errorMessage(response: Response): Promise<string> {
	try {
		const body = await response.json();
		if (Array.isArray(body.violations) && body.violations.length > 0) {
			return body.violations.map((v: { message: string }) => v.message).join(' ');
		}
		return body.detail || body.message || `Erreur ${response.status}`;
	} catch {
		return `Erreur ${response.status}`;
	}
}

async function admin<T>(token: string | null, path: string, init: RequestInit = {}): Promise<T> {
	if (!token) throw new AdminAuthError();
	const response = await request(`/admin${path}`, init, token);
	if (response.status === 401 || response.status === 403) throw new AdminAuthError();
	if (!response.ok) throw new ApiError(response.status, await errorMessage(response));
	return (response.status === 204 ? null : await response.json()) as T;
}

// ── Authentication ──

export async function login(email: string, password: string): Promise<{ token: string; expiresAt: number }> {
	const response = await request('/admin/administrators/token', {
		method: 'POST',
		body: JSON.stringify({ email, password })
	});
	if (!response.ok) throw new ApiError(response.status, 'Identifiants invalides ou compte sans accès API.');
	const { token } = (await response.json()) as { token: string };
	const payload = JSON.parse(Buffer.from(token.split('.')[1], 'base64url').toString()) as { exp?: number };

	return { token, expiresAt: payload.exp ?? Math.floor(Date.now() / 1000) + 3600 };
}

// ── Admin: pages ──

export const adminPages = {
	list: (token: string | null) => admin<CmsPageSummary[]>(token, '/cms-pages'),
	get: (token: string | null, slug: string) => admin<CmsPage>(token, `/cms-pages/${encodeURIComponent(slug)}`),
	create: (token: string | null, page: { slug: string; title: string }) =>
		admin<CmsPage>(token, '/cms-pages', {
			method: 'POST',
			body: JSON.stringify({ ...page, status: 'draft', blocks: [] })
		}),
	update: (token: string | null, slug: string, page: Partial<CmsPage>) =>
		admin<CmsPage>(token, `/cms-pages/${encodeURIComponent(slug)}`, {
			method: 'PUT',
			body: JSON.stringify(page)
		})
};

// ── Shop: published pages and catalog ──

export async function publishedPage(slug: string): Promise<CmsPage | null> {
	const response = await request(`/shop/cms-pages/${encodeURIComponent(slug)}`);
	if (response.status === 404) return null;
	if (!response.ok) throw new ApiError(response.status, await errorMessage(response));
	return (await response.json()) as CmsPage;
}

export async function publishedPages(): Promise<CmsPageSummary[]> {
	const response = await request('/shop/cms-pages');
	return response.ok ? ((await response.json()) as CmsPageSummary[]) : [];
}

/** Media URLs returned by Sylius carry the host of the request (nginx): rebase on the public URL. */
function publicMediaUrl(url: string): string {
	try {
		const { pathname } = new URL(url);
		return `${syliusPublicUrl()}${pathname.replace('/media/cache/resolve/sylius_original/', '/media/cache/resolve/sylius_shop_product_thumbnail/')}`;
	} catch {
		return `${syliusPublicUrl()}/media/image/${url}`;
	}
}

async function product(code: string): Promise<ProductCard | null> {
	const response = await request(`/shop/products/${encodeURIComponent(code)}`);
	if (!response.ok) return null;
	const data = (await response.json()) as { code: string; name: string; slug: string; images: { path: string }[] };

	return {
		code: data.code,
		name: data.name,
		url: `${syliusPublicUrl()}/${LOCALE}/products/${data.slug}`,
		image: data.images[0] ? publicMediaUrl(data.images[0].path) : null
	};
}

async function taxon(code: string): Promise<TaxonCard | null> {
	const response = await request(`/shop/taxons/${encodeURIComponent(code)}`);
	if (!response.ok) return null;
	const data = (await response.json()) as { code: string; name: string; slug: string };

	return { code: data.code, name: data.name, url: `${syliusPublicUrl()}/${LOCALE}/taxons/${data.slug}` };
}

/** Live products / taxons (Sylius shop API, anonymous: no prices). Unknown codes are skipped. */
export async function catalog(productCodes: string[], taxonCodes: string[]): Promise<Catalog> {
	const [products, taxons] = await Promise.all([
		Promise.all(productCodes.map(product)),
		Promise.all(taxonCodes.map(taxon))
	]);

	return {
		products: Object.fromEntries(products.filter((p) => p !== null).map((p) => [p.code, p])),
		taxons: Object.fromEntries(taxons.filter((t) => t !== null).map((t) => [t.code, t]))
	};
}
