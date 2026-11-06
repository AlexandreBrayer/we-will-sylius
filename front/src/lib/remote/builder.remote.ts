// Builder (/cms-admin/builder): CMS pages and templates through the Sylius admin API (builder JWT),
// canvas data (sample product / category, block catalog) from the shop API as a visitor. Served
// under /_app/remote/…: each function checks the builder session (#lib/server/builder.ts). Queries
// redirect to the login form when it expired; commands answer 401 (the page sends to the login form).
import { invalid, redirect } from '@sveltejs/kit';
import { command, form, getRequestEvent, query } from '$app/server';
import * as v from 'valibot';
import type { CmsPage, CmsPageSummary, CmsTemplate, TemplateAssignments, TemplateSummary } from '#lib/blocks/types.js';
import { TOKEN_COOKIE } from '#lib/server/adminAuth.js';
import { asAdmin, canvasCatalog, canvasListing, catalogTaxons, editableTemplate, NEW_TEMPLATE_BLOCKS, templateSample } from '#lib/server/builder.js';
import { pageUrl, safeRedirect } from '#lib/server/session.js';
import { adminLogin, adminPages, adminProductSearch, adminTemplates, ApiError } from '#lib/server/sylius.js';

const Id = v.pipe(v.number(), v.integer(), v.minValue(1));
/** Id in a hidden form field (string). */
const FormId = v.pipe(v.string(), v.toNumber(), v.integer(), v.minValue(1));
const Status = v.picklist(['draft', 'published']);
const TemplateType = v.picklist(['product', 'taxon']);
const Blocks = v.array(v.object({ type: v.string(), props: v.record(v.string(), v.string()) }));
const GrapesData = v.nullable(v.record(v.string(), v.unknown()));
const Codes = v.array(v.string());

/** Sylius validation error (422…): shown by the form instead of a 500. */
function apiInvalid(e: unknown): void {
	if (e instanceof ApiError && e.status < 500) invalid(e.message);
}

// ── Session ──

/**
 * Builder login (/cms-admin/login) = Sylius admin API login, JWT kept in the httpOnly cookie
 * yack_cms_token, then back to `?redirect=` of the login page. Not guarded (served under /_app/remote,
 * or POSTed to /cms-admin/login without JS). `_password` is never sent back to the page.
 */
export const builderLogin = form(
	v.object({
		email: v.pipe(v.string(), v.trim(), v.nonEmpty('Saisissez votre e-mail.')),
		_password: v.pipe(v.string(), v.nonEmpty('Saisissez votre mot de passe.'))
	}),
	async ({ email, _password }) => {
		const event = getRequestEvent();
		const page = pageUrl(event);
		let session: { token: string; expiresAt: number };
		try {
			session = await adminLogin(email, _password);
		} catch (error) {
			if (!(error instanceof ApiError) || error.status >= 500) throw error;
			invalid(error.message);
		}
		event.cookies.set(TOKEN_COOKIE, session.token, {
			path: '/',
			httpOnly: true,
			sameSite: 'lax',
			secure: page.protocol === 'https:',
			maxAge: Math.max(60, session.expiresAt - Math.floor(Date.now() / 1000))
		});
		redirect(303, safeRedirect(page.searchParams.get('redirect'), '/cms-admin/builder'));
	}
);

// ── Pages ──

export const getPages = query((): Promise<CmsPageSummary[]> => asAdmin(getRequestEvent(), (token) => adminPages.list(token)));

export const getPage = query(v.string(), (slug): Promise<CmsPage> =>
	asAdmin(getRequestEvent(), (token) => adminPages.get(token, slug), { notFound: 'Page introuvable' })
);

export const createPage = form(
	v.object({
		title: v.pipe(v.string(), v.trim(), v.nonEmpty('Titre obligatoire.')),
		slug: v.pipe(v.string(), v.trim(), v.nonEmpty('Adresse obligatoire.'))
	}),
	async ({ title, slug }) => {
		try {
			await asAdmin(getRequestEvent(), (token) => adminPages.create(token, { title, slug }));
		} catch (e) {
			apiInvalid(e);
			throw e;
		}
		redirect(303, `/cms-admin/builder/${slug}`);
	}
);

/** Draft / publish from the editor. */
export const savePage = command(
	v.object({
		slug: v.string(),
		title: v.string(),
		metaTitle: v.nullable(v.string()),
		metaDescription: v.nullable(v.string()),
		status: Status,
		blocks: Blocks,
		grapesData: GrapesData
	}),
	async ({ slug, ...page }): Promise<CmsPage> => {
		const saved = await asAdmin(getRequestEvent(), (token) => adminPages.update(token, slug, page as Partial<CmsPage>), {
			command: true
		});
		getPage(slug).set(saved);
		return saved;
	}
);

// ── Templates ──

export const getTemplates = query((): Promise<TemplateSummary[]> => asAdmin(getRequestEvent(), (token) => adminTemplates.list(token)));

export const getTemplate = query(Id, async (id): Promise<CmsTemplate> =>
	editableTemplate(await asAdmin(getRequestEvent(), (token) => adminTemplates.get(token, id), { notFound: 'Template introuvable' }))
);

/** New draft template with the standard blocks of its type, then its editor. */
export const createTemplate = form(
	v.object({
		type: TemplateType,
		name: v.pipe(v.string(), v.trim(), v.nonEmpty('Nom obligatoire.'))
	}),
	async ({ type, name }) => {
		let created: CmsTemplate;
		try {
			created = await asAdmin(getRequestEvent(), (token) => adminTemplates.create(token, { type, name, blocks: NEW_TEMPLATE_BLOCKS[type] }));
		} catch (e) {
			apiInvalid(e);
			throw e;
		}
		redirect(303, `/cms-admin/builder/templates/${created.id}`);
	}
);

/** Draft copy (« … (copie) », no assignment, not default), then its editor. */
export const duplicateTemplate = form(v.object({ id: FormId }), async ({ id }) => {
	const copy = await asAdmin(getRequestEvent(), (token) => adminTemplates.duplicate(token, id), { notFound: 'Template introuvable' });
	redirect(303, `/cms-admin/builder/templates/${copy.id}`);
});

/** Draft / publish from the editor (name, default template of its type, blocks). */
export const saveTemplate = command(
	v.object({
		id: Id,
		name: v.string(),
		isDefault: v.boolean(),
		status: Status,
		blocks: Blocks,
		grapesData: GrapesData
	}),
	async ({ id, ...template }): Promise<CmsTemplate> => {
		const saved = await asAdmin(getRequestEvent(), (token) => adminTemplates.update(token, id, template as Partial<CmsTemplate>), {
			command: true
		});
		getTemplate(id).set(editableTemplate(saved));
		return editableTemplate(saved);
	}
);

export const getTemplateAssignments = query(Id, (id): Promise<TemplateAssignments> =>
	asAdmin(getRequestEvent(), (token) => adminTemplates.assignments(token, id), { notFound: 'Template introuvable' })
);

/** Replaces the products / categories the template is assigned to (each one leaves its previous template). */
export const saveAssignments = command(
	v.object({ id: Id, productCodes: Codes, taxonCodes: Codes }),
	async ({ id, productCodes, taxonCodes }): Promise<TemplateAssignments> => {
		const saved = await asAdmin(getRequestEvent(), (token) => adminTemplates.assign(token, id, { productCodes, taxonCodes }), {
			command: true
		});
		getTemplateAssignments(id).set(saved);
		return saved;
	}
);

/** Products whose name contains / code starts with `q` (admin API: disabled products included). */
export const searchProducts = query(v.pipe(v.string(), v.trim()), (q) =>
	q.length < 2 ? Promise.resolve([]) : asAdmin(getRequestEvent(), (token) => adminProductSearch(token, q, 10))
);

/** Catalog categories matching `q` (name, path or code). */
export const searchTaxons = query(v.pipe(v.string(), v.trim()), (q) => catalogTaxons(getRequestEvent(), q));

// ── Canvas ──

/** Sample product / category of a template preview (`code` null: assigned or catalog default). */
export const getTemplateSample = query(v.object({ id: Id, type: TemplateType, code: v.nullable(v.string()) }), ({ id, type, code }) =>
	templateSample(getRequestEvent(), id, type, code)
);

export const getBlockCatalog = query(v.object({ products: Codes, taxons: Codes }), ({ products, taxons }) =>
	canvasCatalog(getRequestEvent(), products, taxons)
);

export const getPreviewListing = query(
	v.object({
		taxonCode: v.string(),
		sort: v.picklist(['position', 'nom-asc', 'nom-desc', 'recent', 'ancien']),
		perPage: v.pipe(v.number(), v.integer(), v.minValue(1), v.maxValue(27))
	}),
	({ taxonCode, sort, perPage }) => canvasListing(getRequestEvent(), taxonCode, sort, perPage)
);
