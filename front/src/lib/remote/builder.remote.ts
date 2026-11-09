// Builder (/cms-admin/builder): CMS pages and templates through the Sylius admin API (builder JWT),
// canvas data (sample product / category, block catalog) from the shop API as the "Voir comme" profile. Served
// under /_app/remote/…: each function checks the builder session (#lib/server/builder.ts). Queries
// redirect to the login form when it expired; commands answer 401 (the page sends to the login form).
import { invalid, redirect } from '@sveltejs/kit';
import { command, form, getRequestEvent, query } from '$app/server';
import * as v from 'valibot';
import type { CmsPage, CmsPageSummary, CmsTemplate, TemplateAssignments, TemplateSummary } from '#lib/blocks/types.js';
import { asAdmin, canvasCatalog, canvasListing, catalogTaxons, editableTemplate, NEW_TEMPLATE_BLOCKS, templateSample } from '#lib/server/builder.js';
import { AdminAuthError, adminPages, adminProductSearch, adminTemplates, ApiError, request } from '#lib/server/sylius.js';
import { parseBuilderMe, type BuilderMe } from '#lib/editor/panels/blockStyle.js';
import { catalogTree } from '#lib/server/catalog.js';
import { shopClient } from '#lib/server/sylius.js';
import { categoryEntries, describeLink, linkNeeds, matchPages, type LinkDescription, type LinkOption } from '#lib/editor/traits/link.js';
import { MEDIA_ALT_MAX, MEDIA_MESSAGES, mediaIssue, parseMediaItem, parseMediaList, type MediaItem } from '#lib/editor/media/media.js';
import { uploadFormData } from '#lib/server/upload.js';
import { requested } from '$app/server';
import { parseRevision, parseRevisionList, type Revision, type RevisionSummary } from '#lib/editor/panels/history.js';
import { parseSection, parseSectionList, SECTION_BLOCKS_MAX, SECTION_MESSAGES, SECTION_NAME_MAX, type Section } from '#lib/editor/panels/sections.js';
import { parseEditLock, type EditLock } from '#lib/editor/lock.js';

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

// ── Canvas ── (`as`: profile of the "Voir comme" switch; ECOM: channel prices from the admin API)

const As = v.picklist(['visitor', 'pro', 'ecom']);

/** Sample product / category of a template preview (`code` null: assigned or catalog default). */
export const getTemplateSample = query(
	v.object({ id: Id, type: TemplateType, code: v.nullable(v.string()), as: As }),
	({ id, type, code, as }) => templateSample(getRequestEvent(), id, type, code, as)
);

export const getBlockCatalog = query(v.object({ products: Codes, taxons: Codes, as: As }), ({ products, taxons, as }) =>
	canvasCatalog(getRequestEvent(), products, taxons, as)
);

export const getPreviewListing = query(
	v.object({
		taxonCode: v.string(),
		sort: v.picklist(['position', 'nom-asc', 'nom-desc', 'recent', 'ancien']),
		perPage: v.pipe(v.number(), v.integer(), v.minValue(1), v.maxValue(27)),
		as: As
	}),
	({ taxonCode, sort, perPage, as }) => canvasListing(getRequestEvent(), taxonCode, sort, perPage, as)
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

// ── Links (field yack-link, #lib/editor/traits/YackLink.svelte) ──

/**
 * CMS pages (title or slug, drafts included), catalog categories (searchTaxons) and products
 * (searchProducts, 2 characters minimum; only those the shop shows, with their slug) matching `q`.
 * The field adds the shortcuts of the site and the typed address.
 */
export const searchLinks = query(v.pipe(v.string(), v.trim(), v.maxLength(100)), async (q): Promise<LinkOption[]> => {
	const event = getRequestEvent();
	const [pages, taxons, products, tree] = await Promise.all([
		asAdmin(event, (token) => adminPages.list(token)),
		q === '' ? [] : searchTaxons(q),
		searchProducts(q),
		q === '' ? [] : catalogTree()
	]);
	const urls = new Map(categoryEntries(tree).map((entry) => [entry.code, entry.url]));
	const shop = shopClient(null);
	const visible = await Promise.all(
		products.slice(0, 6).map(async ({ code, name }): Promise<LinkOption | null> => {
			const product = await shop.find<{ slug?: string | null }>(`/products/${encodeURIComponent(code)}`);
			return product?.slug ? { value: `/produit/${product.slug}`, label: name, kind: 'product', detail: code } : null;
		})
	);
	return [
		...matchPages(pages, q),
		...taxons.slice(0, 8).flatMap(({ code, path }): LinkOption[] => {
			const url = urls.get(code);
			return url ? [{ value: url, label: path, kind: 'category', detail: url }] : [];
		}),
		...visible.filter((option) => option !== null)
	];
});

/** Readable label of stored links (« Page : Nos services »): one request for the fields of a block. */
export const getLinkLabel = query.batch(v.string(), async (values): Promise<(value: string) => LinkDescription> => {
	const event = getRequestEvent();
	const needs = values.map(linkNeeds);
	const productSlugs = [...new Set(needs.flatMap((need) => (need.product === undefined ? [] : [need.product])))];
	const shop = shopClient(null);
	const [pages, tree, products] = await Promise.all([
		needs.some((need) => need.page !== undefined) ? asAdmin(event, (token) => adminPages.list(token)) : [],
		needs.some((need) => need.category !== undefined) ? catalogTree() : [],
		Promise.all(
			productSlugs.map(async (slug) => [slug, (await shop.find<{ name?: string }>(`/products-by-slug/${encodeURIComponent(slug)}`))?.name] as const)
		)
	]);
	const names = {
		pages: new Map(pages.map((page) => [page.slug, page.title])),
		categories: new Map(categoryEntries(tree).map((entry) => [entry.url, entry.path])),
		products: new Map(products.flatMap(([slug, name]) => (name ? [[slug, name] as const] : [])))
	};
	return (value) => describeLink(value, names);
});

// ── Media library (D7) ──

/**
 * Call of the media API (`/admin/cms-media…`) with the builder JWT: JSON or multipart body (FormData sent as
 * is), the French message of a refusal in ApiError (violations joined, or `detail` of a 409).
 */
async function mediaCall(token: string | null, path: string, options: Parameters<typeof request>[1] = {}): Promise<unknown> {
	if (!token) throw new AdminAuthError();
	const response = await request(`/admin${path}`, { ...options, token });
	if (response.status === 401 || response.status === 403) throw new AdminAuthError();
	const body: unknown = await response.json().catch(() => null);
	if (!response.ok) {
		const failure = (typeof body === 'object' && body !== null ? body : {}) as { violations?: { propertyPath: string; message: string }[]; detail?: string };
		const violations = Array.isArray(failure.violations) ? failure.violations : [];
		const message = violations.length > 0 ? violations.map((violation) => violation.message).join(' ') : failure.detail || `Erreur ${response.status}`;
		throw new ApiError(response.status, message, violations);
	}
	return body;
}

/** Images of the library, the most recent first; `q`: part of the name (case ignored). */
export const getMediaLibrary = query(v.pipe(v.string(), v.trim(), v.maxLength(100)), (q): Promise<MediaItem[]> =>
	asAdmin(getRequestEvent(), async (token) => parseMediaList(await mediaCall(token, '/cms-media', { query: { q } })), { command: true })
);

/** File field of the upload: the rules of the browser (`mediaIssue`), checked again here; the API reads the content. */
const mediaFile = v.pipe(
	v.file(MEDIA_MESSAGES.missing),
	v.check(
		(file) => mediaIssue(file) === null,
		(issue) => mediaIssue(issue.input as File) ?? MEDIA_MESSAGES.format
	)
);

/**
 * Upload of one image (multipart, `enctype="multipart/form-data"`, one file per submission): the image
 * created, or the refusal of the API (422: SVG, PDF renamed, empty, too large…) as an issue of the form.
 * An expired session is an error 401 (no redirection: the editor keeps its unsaved changes).
 */
export const uploadMedia = form(
	v.object({ file: mediaFile, alt: v.optional(v.pipe(v.string(), v.trim(), v.maxLength(MEDIA_ALT_MAX, MEDIA_MESSAGES.altTooLong))) }, MEDIA_MESSAGES.missing),
	async ({ file, alt }): Promise<MediaItem> =>
		asAdmin(
			getRequestEvent(),
			async (token) => {
				try {
					const created = parseMediaItem(await mediaCall(token, '/cms-media', { method: 'POST', body: await uploadFormData(alt ? { alt } : {}, { file }) }));
					if (!created) throw new ApiError(502, 'Réponse inattendue de la médiathèque.');
					// The lists of the library shown by the window (`submit().updates(getMediaLibrary(q))`).
					await requested(getMediaLibrary, 5).refreshAll();
					return created;
				} catch (e) {
					if (e instanceof ApiError && e.status < 500) invalid(e.message);
					throw e;
				}
			},
			{ command: true }
		)
);

/** Default alternative text of an image of the library (PUT /cms-media/{id}). */
export const saveMediaAlt = command(
	v.object({ id: Id, alt: v.pipe(v.string(), v.trim(), v.maxLength(MEDIA_ALT_MAX, MEDIA_MESSAGES.altTooLong)) }),
	async ({ id, alt }): Promise<MediaItem> =>
		asAdmin(
			getRequestEvent(),
			async (token) => {
				const saved = parseMediaItem(await mediaCall(token, `/cms-media/${id}`, { method: 'PUT', body: { alt } }));
				if (!saved) throw new ApiError(502, 'Réponse inattendue de la médiathèque.');
				await requested(getMediaLibrary, 5).refreshAll();
				return saved;
			},
			{ command: true }
		)
);

// ── History (D11, #lib/editor/panels/history.ts) ──

/** Content whose history is read: a page by its slug, a template by its id. */
const RevisionTarget = v.variant('target', [
	v.object({ target: v.literal('page'), slug: v.pipe(v.string(), v.nonEmpty(), v.maxLength(255)) }),
	v.object({ target: v.literal('template'), id: Id })
]);

/**
 * Revisions of a page or a template, the most recent first (50 at most; GET /cms-pages/{slug}/revisions or
 * /cms-templates/{id}/revisions). Read-only API of the admin, same call as the media library (French
 * message of a refusal; an expired session is an error 401, the editor keeps its unsaved changes).
 */
export const getRevisions = query(RevisionTarget, (target): Promise<RevisionSummary[]> =>
	asAdmin(
		getRequestEvent(),
		async (token) =>
			parseRevisionList(
				await mediaCall(token, target.target === 'page' ? `/cms-pages/${encodeURIComponent(target.slug)}/revisions` : `/cms-templates/${target.id}/revisions`)
			),
		{ command: true }
	)
);

/** A revision and its blocks (GET /cms-revisions/{id}); 404 when it was purged (51st save) or its content deleted. */
export const getRevision = query(Id, (id): Promise<Revision> =>
	asAdmin(
		getRequestEvent(),
		async (token) => {
			const revision = parseRevision(await mediaCall(token, `/cms-revisions/${id}`));
			if (!revision) throw new ApiError(502, 'Réponse inattendue de l’historique.');
			return revision;
		},
		{ command: true }
	)
);

// ── Sections (D12, #lib/editor/panels/sections.ts) ──

/** Name of a section: Sylius cleans it to one line and checks it again (422 on `name`). */
const SectionName = v.pipe(v.string(), v.trim(), v.nonEmpty(SECTION_MESSAGES.nameMissing), v.maxLength(SECTION_NAME_MAX, SECTION_MESSAGES.nameTooLong));

/**
 * Sections of « Mes sections », shared by the administrators, by name (GET /cms-sections). Same call as the
 * media library: French message of a refusal; an expired session is an error 401, the editor keeps its
 * unsaved changes. The editor reads the list again after each change (`getSections().refresh()`).
 */
export const getSections = query((): Promise<Section[]> =>
	asAdmin(getRequestEvent(), async (token) => parseSectionList(await mediaCall(token, '/cms-sections')), { command: true })
);

/**
 * « Enregistrer comme section »: the blocks as the editor saves them (props and style). Sylius validates them
 * as the blocks of a page, at the level of the account (422: free value of a Charte account, bound block…).
 */
export const createSection = command(
	v.object({ name: SectionName, blocks: v.pipe(Blocks, v.minLength(1, SECTION_MESSAGES.noBlock), v.maxLength(SECTION_BLOCKS_MAX, SECTION_MESSAGES.tooManyBlocks)) }),
	async ({ name, blocks }): Promise<Section> =>
		asAdmin(
			getRequestEvent(),
			async (token) => {
				const created = parseSection(await mediaCall(token, '/cms-sections', { method: 'POST', body: { name, blocks } }));
				if (!created) throw new ApiError(502, 'Réponse inattendue des sections.');
				return created;
			},
			{ command: true }
		)
);

/** Renames a section (PUT /cms-sections/{id}); its blocks never change. */
export const renameSection = command(v.object({ id: Id, name: SectionName }), async ({ id, name }): Promise<Section> =>
	asAdmin(
		getRequestEvent(),
		async (token) => {
			const saved = parseSection(await mediaCall(token, `/cms-sections/${id}`, { method: 'PUT', body: { name } }));
			if (!saved) throw new ApiError(502, 'Réponse inattendue des sections.');
			return saved;
		},
		{ command: true }
	)
);

/** Deletes a section (DELETE /cms-sections/{id}): the pages where it was inserted keep their copies. */
export const deleteSection = command(Id, async (id): Promise<void> =>
	asAdmin(
		getRequestEvent(),
		async (token) => {
			await mediaCall(token, `/cms-sections/${id}`, { method: 'DELETE' });
		},
		{ command: true }
	)
);

// ── Edit lock (D13, #lib/editor/lock.ts) ──

/** Route of the lock of a page (slug) or a template (id), as for the history. */
const lockPath = (target: v.InferOutput<typeof RevisionTarget>): string =>
	target.target === 'page' ? `/cms-pages/${encodeURIComponent(target.slug)}/lock` : `/cms-templates/${target.id}/lock`;

/** Lock answer of Sylius, read; an unexpected one is an error (the editor keeps its state). */
function editLockOf(raw: unknown): EditLock {
	const lock = parseEditLock(raw);
	if (!lock) throw new ApiError(502, 'Réponse inattendue du verrou d’édition.');
	return lock;
}

/**
 * Lock of the content as the logged-in administrator sees it (GET …/lock). A command, not a query: read
 * again at each tick of a read-only editor, never cached. An expired session is an error 401.
 */
export const readEditLock = command(RevisionTarget, async (target): Promise<EditLock> =>
	asAdmin(getRequestEvent(), async (token) => editLockOf(await mediaCall(token, lockPath(target))), { command: true })
);

/**
 * Acquire (opening), heartbeat (every 30 s), takeover (« Prendre la main »), release (closing) (POST …/lock):
 * the lock after the operation. The release at `pagehide` goes through /cms-admin/verrou (sendBeacon).
 */
export const editLock = command(
	v.object({
		target: RevisionTarget,
		action: v.picklist(['acquire', 'heartbeat', 'takeover', 'release']),
		token: v.nullable(v.pipe(v.string(), v.regex(/^[0-9a-f]{32}$/)))
	}),
	async ({ target, action, token }): Promise<EditLock> =>
		asAdmin(
			getRequestEvent(),
			async (jwt) => editLockOf(await mediaCall(jwt, lockPath(target), { method: 'POST', body: token ? { action, token } : { action } })),
			{ command: true }
		)
);
