// CMS templates of the common pages (docs/API_FRONT.md §11): the product page and the category
// pages are rendered through the template Sylius resolves (product > category > default), whose
// bound blocks read the context built here (ProductContext / TaxonContext).
import { error } from '@sveltejs/kit';
import type {
	BlockProps,
	Catalog,
	CmsTemplate,
	PageBlock,
	ProductContext,
	ResolvedTemplate,
	TaxonContext,
	TaxonListing,
	TemplateType
} from '#lib/blocks/types.js';
import type { ListingSearch, SortKey, TaxonNode, Viewer } from '#lib/types.js';
import {
	breadcrumb,
	catalogTree,
	findProduct,
	LISTING_DEFAULTS,
	listingParams,
	listProducts,
	PER_PAGE,
	SECTIONS,
	sortOptions,
	taxonPath
} from './catalog.js';
import { catalogOfBlocks } from './cms.js';
import { AdminAuthError, adminTemplates, ApiError, sylius, type ShopClient, type ShopEvent } from './sylius.js';

export type SectionUrl = (typeof SECTIONS)[number]['url'];

/** Initial sorts of "categorie-produits" (BlockType::GRID_SORTS: no price sort, prices depend on the viewer). */
const GRID_SORTS: SortKey[] = ['position', 'nom-asc', 'nom-desc', 'recent', 'ancien'];

/** Blocks as the page renders them: what a template page needs (no template name / id for shoppers). */
export interface TemplatePage<C> {
	blocks: PageBlock[];
	context: C;
	/** Products / taxons of the free blocks "produits" / "categories". */
	catalog: Catalog;
	/** Draft / other template previewed by an administrator logged into the builder (?apercu=<id>). */
	preview: { id: number; name: string; status: CmsTemplate['status'] } | null;
}

/** Used when no published template applies (no default template): the "Standard" layouts. */
const FALLBACK: Record<TemplateType, PageBlock[]> = {
	product: [
		{ type: 'produit-galerie', props: {} },
		{ type: 'produit-infos', props: {} },
		{ type: 'produit-achat', props: {} },
		{ type: 'produit-sav', props: { title: '', text: '' } },
		{ type: 'produit-description', props: { title: '' } },
		{ type: 'produit-caracteristiques', props: { title: '' } }
	],
	taxon: [
		{ type: 'categorie-entete', props: { description: 'oui' } },
		{ type: 'categorie-produits', props: { perPage: '9', sort: 'position' } }
	]
};

/** Block props as sent by Sylius: a block without props comes as `[]` (empty PHP array). */
export function normalizeBlocks(blocks: { type: string; props: BlockProps | unknown[] }[]): PageBlock[] {
	return blocks.map((block) => ({
		type: block.type as PageBlock['type'],
		props: Array.isArray(block.props) ? {} : { ...block.props }
	}));
}

/** Published template applying to a product / category; null when none (no default template). */
async function resolveTemplate(api: ShopClient, type: TemplateType, code: string): Promise<ResolvedTemplate | null> {
	const template = await api.find<ResolvedTemplate>('/cms-templates/resolve', { type, code });
	return template ? { ...template, blocks: normalizeBlocks(template.blocks) } : null;
}

/**
 * Blocks of the page: the resolved template, or the template `previewId` (?apercu=<id>, any
 * status) when asked by an administrator logged into the builder; with the catalog of its free blocks.
 */
async function pageBlocks(
	event: ShopEvent,
	type: TemplateType,
	code: string,
	previewId: number | null
): Promise<Omit<TemplatePage<unknown>, 'context'>> {
	const { locals } = event;
	const api = sylius(event);
	let blocks: PageBlock[] | null = null;
	let preview: TemplatePage<unknown>['preview'] = null;

	if (previewId !== null && locals.adminToken) {
		try {
			const template = await adminTemplates.get(locals.adminToken, previewId);
			if (template.type === type) {
				blocks = normalizeBlocks(template.blocks);
				preview = { id: template.id, name: template.name, status: template.status };
			}
		} catch (e) {
			// Unknown template / expired builder session: the page as shoppers see it (queries cannot drop cookies).
			if (!(e instanceof AdminAuthError || (e instanceof ApiError && e.status === 404))) throw e;
		}
	}
	blocks ??= (await resolveTemplate(api, type, code))?.blocks ?? FALLBACK[type];

	return { blocks, preview, catalog: await catalogOfBlocks(api, locals.viewer, blocks) };
}

// ── Product page ──

/** Largest "produits-associes" limit of the template (0 = no such block: nothing to load). */
function relatedLimit(blocks: PageBlock[]): number {
	return Math.max(0, ...blocks.filter((b) => b.type === 'produits-associes').map((b) => Number(b.props.limit) || 4));
}

/**
 * Product context (product page, builder sample) with the template blocks it is rendered with:
 * `blocksOf` gets the product code and gives the blocks (related products loaded only when shown).
 */
async function productContext<B extends { blocks: PageBlock[] }>(
	api: ShopClient,
	viewer: Viewer,
	by: { slug: string } | { code: string },
	blocksOf: (code: string) => Promise<B>
): Promise<(B & { context: ProductContext }) | null> {
	const [found, roots] = await Promise.all([findProduct(api, viewer, by), catalogTree()]);
	if (!found) return null;
	const { product } = found;
	const trail = product.mainTaxonCode ? taxonPath(roots, (node) => node.code === product.mainTaxonCode) : [];
	const template = await blocksOf(product.code);

	return {
		...template,
		context: { product, breadcrumb: breadcrumb(trail, product.name), related: await found.related(relatedLimit(template.blocks)) }
	};
}

/** /produit/[slug]: product, template blocks, related products when the template shows them; 404 when unknown. */
export async function productPage(event: ShopEvent, slug: string, previewId: number | null): Promise<TemplatePage<ProductContext>> {
	const page = await productContext(sylius(event), event.locals.viewer, { slug }, (code) => pageBlocks(event, 'product', code, previewId));
	if (!page) error(404, 'Produit introuvable');
	return page;
}

/** Builder sample product (visitor view), with up to 8 related products (the block shows 4 or 8). */
export async function productSample(api: ShopClient, viewer: Viewer, code: string): Promise<ProductContext | null> {
	const sample = await productContext(api, viewer, { code }, async () => ({
		blocks: [{ type: 'produits-associes' as const, props: { limit: '8' } }]
	}));
	return sample?.context ?? null;
}

// ── Category page ──

/** Initial sort / page size of the "categorie-produits" block. */
function gridDefaults(blocks: PageBlock[]): { sort: SortKey; perPage: number } {
	const props = blocks.find((b) => b.type === 'categorie-produits')?.props ?? {};
	const perPage = Number(props.perPage);
	const sort = GRID_SORTS.find((key) => key === props.sort);

	return {
		sort: sort ?? LISTING_DEFAULTS.sort,
		perPage: (PER_PAGE as readonly number[]).includes(perPage) ? perPage : LISTING_DEFAULTS.perPage
	};
}

/** Products of a category: URL state (`search`) over the block's initial sort / page size. */
export async function taxonListing(
	api: ShopClient,
	viewer: Viewer,
	taxonCode: string,
	search: ListingSearch,
	defaults: { sort: SortKey; perPage: number }
): Promise<TaxonListing> {
	const params = listingParams(search, viewer, defaults);
	return {
		result: await listProducts(api, viewer, { ...params, taxonCode }),
		params,
		defaults,
		sorts: sortOptions(viewer)
	};
}

function taxonContext(trail: TaxonNode[], listing: TaxonListing): TaxonContext {
	const taxon = trail[trail.length - 1];
	return {
		taxon: { code: taxon.code, name: taxon.name, description: taxon.description, url: taxon.url },
		breadcrumb: breadcrumb(trail),
		children: taxon.children.map((child) => ({ code: child.code, name: child.name, url: child.url })),
		up: trail.at(-2)?.url ?? null,
		listing
	};
}

/** /produits/…, /pieces/…: `path` = rest of the URL (taxon slugs below the section root); 404 when unknown. */
export async function taxonPage(
	event: ShopEvent,
	section: SectionUrl,
	path: string,
	search: ListingSearch,
	previewId: number | null
): Promise<TemplatePage<TaxonContext>> {
	const url = path ? `${section}/${path.replace(/\/+$/, '')}` : section;
	const trail = taxonPath(await catalogTree(), (node) => node.url === url);
	const taxon = trail.at(-1);
	if (!taxon) error(404, 'Catégorie introuvable');

	const { blocks, preview, catalog } = await pageBlocks(event, 'taxon', taxon.code, previewId);
	const listing = await taxonListing(sylius(event), event.locals.viewer, taxon.code, search, gridDefaults(blocks));

	return { blocks, context: taxonContext(trail, listing), catalog, preview };
}

/** Builder sample category (visitor view): context with the first page of its products. */
export async function taxonSample(api: ShopClient, viewer: Viewer, code: string): Promise<TaxonContext | null> {
	const trail = taxonPath(await catalogTree(), (node) => node.code === code);
	if (trail.length === 0) return null;
	return taxonContext(trail, await taxonListing(api, viewer, code, {}, LISTING_DEFAULTS));
}
