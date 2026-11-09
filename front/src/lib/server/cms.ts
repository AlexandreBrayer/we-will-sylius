// CMS page of the shop (homepage "accueil", /<slug>) with the products / taxons of its blocks.
import { redirect } from '@sveltejs/kit';
import { parseCodes } from '#lib/blocks/parse.js';
import type { Catalog, CmsPage, PageBlock } from '#lib/blocks/types.js';
import type { Viewer } from '#lib/types.js';
import { adminLoginUrl } from './adminAuth.js';
import { blockCatalog, publishedPage } from './catalog.js';
import { pageUrl } from './session.js';
import { AdminAuthError, adminPages, ApiError, sylius, type ShopClient, type ShopEvent } from './sylius.js';

/** Products / taxons shown by the free blocks "produits" / "categories" (pages and templates). */
export function catalogOfBlocks(api: ShopClient, viewer: Viewer, blocks: PageBlock[]): Promise<Catalog> {
	const productCodes = blocks.filter((b) => b.type === 'produits').flatMap((b) => parseCodes(b.props.productCodes));
	const taxonCodes = blocks.filter((b) => b.type === 'categories').flatMap((b) => parseCodes(b.props.taxonCodes));
	return blockCatalog(api, viewer, [...new Set(productCodes)], [...new Set(taxonCodes)]);
}

export interface CmsPageData {
	page: CmsPage;
	/** Draft preview by an administrator logged into the builder (?apercu=1). */
	preview: boolean;
	catalog: Catalog;
}

/**
 * Published page, or its draft when `preview` is asked (?apercu=1) by an administrator logged
 * into the builder; null when it does not exist / is not published.
 */
export async function loadCmsPage(event: ShopEvent, slug: string, askPreview: boolean): Promise<CmsPageData | null> {
	const { locals } = event;
	const api = sylius(event);
	const preview = askPreview && locals.adminToken !== null;

	let page: CmsPage | null;
	if (preview) {
		try {
			page = await adminPages.get(locals.adminToken, slug);
		} catch (error) {
			// Expired builder session: back to the login form (a query cannot drop the cookie; the login replaces it).
			if (error instanceof AdminAuthError) redirect(303, adminLoginUrl(pageUrl(event)));
			if (!(error instanceof ApiError && error.status === 404)) throw error;
			page = null;
		}
	} else {
		page = await publishedPage(api, slug);
	}
	if (!page) return null;

	return { page, preview, catalog: await catalogOfBlocks(api, locals.viewer, page.blocks) };
}
