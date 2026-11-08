// Builder (/cms-admin/builder): CMS pages and templates through the Sylius admin API (builder JWT),
// canvas data (sample product / category, block catalog) from the shop API as a visitor. Served
// under /_app/remote/…: each function checks the builder session (#lib/server/builder.ts). Queries
// redirect to the login form when it expired; commands answer 401 (the page sends to the login form).
import { invalid, redirect } from '@sveltejs/kit';
import { command, form, getRequestEvent, query } from '$app/server';
import * as v from 'valibot';
import type { CmsPage, CmsPageSummary, CmsTemplate, TemplateAssignments, TemplateSummary } from '#lib/blocks/types.js';
import { asAdmin, canvasCatalog, canvasListing, catalogTaxons, editableTemplate, NEW_TEMPLATE_BLOCKS, templateSample } from '#lib/server/builder.js';
import { AdminAuthError, adminPages, adminProductSearch, adminTemplates, ApiError, request } from '#lib/server/sylius.js';
import { parseBuilderMe, type BuilderMe } from '#lib/editor/panels/blockStyle.js';

const Id = v.pipe(v.number(), v.integer(), v.minValue(1));
/** Id in a hidden form field (string). */
const FormId = v.pipe(v.string(), v.toNumber(), v.integer(), v.minValue(1));
const Status = v.picklist(['draft', 'published']);
const TemplateType = v.picklist(['product', 'taxon']);
// `style`: named style of the block (BlockStyle), validated by Sylius; never `[]` (the editor reads it with readStyle).
const Blocks = v.array(v.object({ type: v.string(), props: v.record(v.string(), v.string()), style: v.optional(v.record(v.string(), v.string())) }));
const GrapesData = v.nullable(v.record(v.string(), v.unknown()));
const Codes = v.array(v.string());

/** Sylius validation error (422…): shown by the form instead of a 500. */
function apiInvalid(e: unknown): void {
	if (e instanceof ApiError && e.status < 500) invalid(e.message);
}

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

/**
 * Draft copy (« … (copie) », no assignment, not default), then its editor. A refusal of Sylius (422:
 * a Charte account copying a template that holds a value outside the charte) is shown by the form.
 */
export const duplicateTemplate = form(v.object({ id: FormId }), async ({ id }) => {
	let copy: CmsTemplate;
	try {
		copy = await asAdmin(getRequestEvent(), (token) => adminTemplates.duplicate(token, id), { notFound: 'Template introuvable' });
	} catch (e) {
		apiInvalid(e);
		throw e;
	}
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

// ── Administrator ──

/** Level in the builder and charte of the logged-in administrator (GET /admin/yack/builder/me). */
export const getBuilderMe = query((): Promise<BuilderMe> =>
	asAdmin(getRequestEvent(), async (token) => {
		if (!token) throw new AdminAuthError();
		const response = await request('/admin/yack/builder/me', { token });
		if (response.status === 401 || response.status === 403) throw new AdminAuthError();
		if (!response.ok) throw new ApiError(response.status, `Erreur ${response.status}`);
		return parseBuilderMe(await response.json());
	})
);
