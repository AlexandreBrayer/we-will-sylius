// Typed client for the Sylius API (server side only: the browser never talks to Sylius).
//  - shop API: `sylius(event)` — sends the customer JWT of the session (cookie yack_session);
//    an expired / revoked JWT ends the session and redirects to /connexion.
//  - admin API: `adminPages`, `adminTemplates` — JWT of a Sylius administrator (builder, cookie yack_cms_token).
import { redirect, type RequestEvent } from '@sveltejs/kit';
import { SYLIUS_API_URL } from '$app/env/private';
import type { CmsPage, CmsPageSummary, CmsTemplate, TemplateAssignments, TemplateSummary } from '#lib/blocks/types.js';
import { endSession, loginUrl, pageUrl } from './session.js';


const LOCALE = 'fr_FR';

export type QueryValue = string | number | boolean | null | undefined;
export type Query = Record<string, QueryValue | QueryValue[]>;

export interface Violation {
	propertyPath: string;
	message: string;
}

/** Non-2xx answer of the API. `message` is the French message to show (violations joined). */
export class ApiError extends Error {
	constructor(
		readonly status: number,
		message: string,
		readonly violations: Violation[] = []
	) {
		super(message);
	}
}

/** The builder JWT is missing, expired or not an administrator one. */
export class AdminAuthError extends Error {}

export interface Collection<T> {
	items: T[];
	total: number;
}

export interface RequestOptions {
	method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
	query?: Query;
	/**
	 * JSON body (PATCH is sent as application/merge-patch+json, as API Platform expects), or a
	 * FormData sent as is: fetch writes the multipart/form-data Content-Type with its boundary.
	 */
	body?: unknown;
	/** JWT sent as Bearer token. */
	token?: string | null;
	/** JSON-LD (hydra) answer: needed for collection totals. */
	ld?: boolean;
}

function buildUrl(path: string, query?: Query): string {
	// Sylius as reached by this server (Docker: http://nginx, compose.yml; see src/env.ts).
	const url = new URL(`${SYLIUS_API_URL}/api/v2${path}`);
	for (const [name, value] of Object.entries(query ?? {})) {
		for (const item of Array.isArray(value) ? value : [value]) {
			if (item !== null && item !== undefined && item !== '') url.searchParams.append(name, String(item));
		}
	}
	return url.toString();
}

/** Raw call to the API; no error handling (see `call`). */
export async function request(path: string, options: RequestOptions = {}): Promise<Response> {
	const { method = 'GET', query, body, token, ld = false } = options;
	const headers = new Headers({
		Accept: ld ? 'application/ld+json' : 'application/json',
		'Accept-Language': LOCALE
	});
	const multipart = body instanceof FormData;
	if (body !== undefined && !multipart) headers.set('Content-Type', method === 'PATCH' ? 'application/merge-patch+json' : 'application/json');
	if (token) headers.set('Authorization', `Bearer ${token}`);

	return fetch(buildUrl(path, query), {
		method,
		headers,
		body: body === undefined ? undefined : multipart ? body : JSON.stringify(body)
	});
}

/** French message of an API Platform / Sylius error (violations joined). */
async function apiError(response: Response): Promise<ApiError> {
	try {
		const body = (await response.json()) as { violations?: Violation[]; detail?: string; message?: string; 'hydra:description'?: string };
		const violations = Array.isArray(body.violations) ? body.violations : [];
		const message =
			violations.length > 0
				? violations.map((v) => v.message).join(' ')
				: body.detail || body['hydra:description'] || body.message || `Erreur ${response.status}`;
		return new ApiError(response.status, message, violations);
	} catch {
		return new ApiError(response.status, `Erreur ${response.status}`);
	}
}

async function call<T>(path: string, options: RequestOptions = {}): Promise<T> {
	const response = await request(path, options);
	if (!response.ok) throw await apiError(response);
	// 204, and 202 with an empty body (POST /customers/reset-password, PATCH /customers/reset-password/{token}).
	const body = await response.text();
	return (body === '' ? null : JSON.parse(body)) as T;
}

function toCollection<T>(body: Record<string, unknown>): Collection<T> {
	const items = (body['hydra:member'] ?? body.member ?? []) as T[];
	const total = (body['hydra:totalItems'] ?? body.totalItems ?? items.length) as number;
	return { items, total };
}

// ── Shop API, bound to the session of the request ──

export interface ShopClient {
	/** GET an item; `null` on 404. */
	find<T>(path: string, query?: Query): Promise<T | null>;
	get<T>(path: string, query?: Query): Promise<T>;
	/** GET a paginated collection (JSON-LD) with its total. */
	collection<T>(path: string, query?: Query): Promise<Collection<T>>;
	post<T>(path: string, body?: unknown): Promise<T>;
	/** POST multipart/form-data (files): check them first with `#lib/server/upload.ts`, the API stays the judge. */
	postForm<T>(path: string, form: FormData): Promise<T>;
	put<T>(path: string, body: unknown): Promise<T>;
	patch<T>(path: string, body: unknown): Promise<T>;
	delete(path: string): Promise<void>;
}

/** Shop API client (`path` relative to /api/v2/shop); `onExpired` runs when the API rejects `token`. */
export function shopClient(token: string | null, onExpired?: () => never): ShopClient {
	async function shop<T>(path: string, options: RequestOptions): Promise<T> {
		try {
			return await call<T>(`/shop${path}`, { ...options, token });
		} catch (error) {
			if (error instanceof ApiError && error.status === 401 && token && onExpired) onExpired();
			throw error;
		}
	}

	return {
		async find<T>(path: string, query?: Query) {
			try {
				return await shop<T>(path, { query });
			} catch (error) {
				if (error instanceof ApiError && error.status === 404) return null;
				throw error;
			}
		},
		get: <T>(path: string, query?: Query) => shop<T>(path, { query }),
		collection: async <T>(path: string, query?: Query) =>
			toCollection<T>(await shop<Record<string, unknown>>(path, { query, ld: true })),
		post: <T>(path: string, body: unknown = {}) => shop<T>(path, { method: 'POST', body }),
		postForm: <T>(path: string, form: FormData) => shop<T>(path, { method: 'POST', body: form }),
		put: <T>(path: string, body: unknown) => shop<T>(path, { method: 'PUT', body }),
		patch: <T>(path: string, body: unknown) => shop<T>(path, { method: 'PATCH', body }),
		delete: async (path: string) => {
			await shop<null>(path, { method: 'DELETE' });
		}
	};
}

/** What `sylius()` needs from the request (a load / action event or `getRequestEvent()`). */
export type ShopEvent = Pick<RequestEvent, 'locals' | 'cookies' | 'url' | 'request' | 'isRemoteRequest'>;

/**
 * Shop API client of the current request, authenticated with the customer JWT of the session.
 * A 401 means the session is over: cookies are dropped and the visitor is sent to /connexion,
 * coming back to the page afterwards (thrown redirect — works from loads, actions and remote
 * functions, where the page is the Referer).
 */
export function sylius(event: ShopEvent): ShopClient {
	return shopClient(event.locals.sessionToken, () => {
		endSession(event.cookies);
		redirect(303, loginUrl(pageUrl(event), { expired: true }));
	});
}

// ── Customer authentication ──

/** Shop JWT of a customer (`POST /shop/customers/token`). */
export async function customerToken(email: string, password: string): Promise<string> {
	const { token } = await call<{ token: string }>('/shop/customers/token', { method: 'POST', body: { email, password } });
	return token;
}

/** Expiry (unix seconds) carried by a JWT, `null` when unreadable. */
export function jwtExpiry(token: string): number | null {
	try {
		const payload = JSON.parse(Buffer.from(token.split('.')[1], 'base64url').toString()) as { exp?: number };
		return typeof payload.exp === 'number' ? payload.exp : null;
	} catch {
		return null;
	}
}

// ── Admin API (builder) ──

async function admin<T>(token: string | null, path: string, options: RequestOptions = {}): Promise<T> {
	if (!token) throw new AdminAuthError();
	try {
		return await call<T>(`/admin${path}`, { ...options, token });
	} catch (error) {
		if (error instanceof ApiError && (error.status === 401 || error.status === 403)) throw new AdminAuthError();
		throw error;
	}
}

export const adminPages = {
	list: (token: string | null) => admin<CmsPageSummary[]>(token, '/cms-pages'),
	get: (token: string | null, slug: string) => admin<CmsPage>(token, `/cms-pages/${encodeURIComponent(slug)}`),
	create: (token: string | null, page: { slug: string; title: string }) =>
		admin<CmsPage>(token, '/cms-pages', { method: 'POST', body: { ...page, status: 'draft', blocks: [] } }),
	update: (token: string | null, slug: string, page: Partial<CmsPage>) =>
		admin<CmsPage>(token, `/cms-pages/${encodeURIComponent(slug)}`, { method: 'PUT', body: page })
};

export const adminTemplates = {
	list: (token: string | null) => admin<TemplateSummary[]>(token, '/cms-templates'),
	get: (token: string | null, id: number) => admin<CmsTemplate>(token, `/cms-templates/${id}`),
	create: (token: string | null, template: Pick<CmsTemplate, 'type' | 'name' | 'blocks'>) =>
		admin<CmsTemplate>(token, '/cms-templates', { method: 'POST', body: { ...template, status: 'draft' } }),
	update: (token: string | null, id: number, template: Partial<Pick<CmsTemplate, 'name' | 'status' | 'isDefault' | 'blocks' | 'grapesData'>>) =>
		admin<CmsTemplate>(token, `/cms-templates/${id}`, { method: 'PUT', body: template }),
	duplicate: (token: string | null, id: number) => admin<CmsTemplate>(token, `/cms-templates/${id}/duplicate`, { method: 'POST' }),
	assignments: (token: string | null, id: number) => admin<TemplateAssignments>(token, `/cms-templates/${id}/assignments`),
	assign: (token: string | null, id: number, codes: Pick<TemplateAssignments, 'productCodes' | 'taxonCodes'>) =>
		admin<TemplateAssignments>(token, `/cms-templates/${id}/assignments`, { method: 'PUT', body: codes })
};

/** Builder product search (assignments, sample product): name contains `q` or code starts with `q`. */
export async function adminProductSearch(token: string | null, q: string, limit: number): Promise<{ code: string; name: string }[]> {
	type AdminProduct = { code: string; name?: string | null; translations?: Record<string, { name?: string }> };
	const [byName, byCode] = await Promise.all([
		admin<AdminProduct[]>(token, '/products', { query: { 'translations.name': q, itemsPerPage: limit } }),
		admin<AdminProduct[]>(token, '/products', { query: { code: q, itemsPerPage: limit } })
	]);
	const products = new Map<string, string>();
	for (const product of [...byCode, ...byName]) {
		products.set(product.code, product.translations?.[LOCALE]?.name ?? product.name ?? product.code);
	}
	return [...products].slice(0, limit).map(([code, name]) => ({ code, name }));
}

/** Channel of the shop (config/packages/yack.yaml: app.yack.channel_code). */
const CHANNEL_CODE = 'YACK_WEB';

/** Price of a variant on the channel, in cents (originalPrice: price before catalog promotions). */
export interface ChannelPrice {
	price: number;
	originalPrice: number;
}

/**
 * Channel price of a variant (admin API, catalog promotions applied as on the shop): what an ECOM
 * account pays — the shop API hides it from visitors. null: no pricing on the channel.
 */
export async function adminChannelPrice(token: string | null, variantCode: string): Promise<ChannelPrice | null> {
	type AdminVariant = { channelPricings?: Record<string, { price: number | null; originalPrice: number | null }> };
	const pricing = (await admin<AdminVariant>(token, `/product-variants/${encodeURIComponent(variantCode)}`)).channelPricings?.[CHANNEL_CODE];
	if (!pricing || pricing.price === null) return null;
	return { price: pricing.price, originalPrice: pricing.originalPrice ?? pricing.price };
}
