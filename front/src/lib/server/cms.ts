// CMS page of the shop (homepage "accueil", /<slug>) with the products / taxons of its blocks.
import type { RequestEvent } from '@sveltejs/kit';
import { parseCodes } from '#lib/blocks/parse.js';
import type { Catalog, CmsPage } from '#lib/blocks/types.js';
import { handleAuthError } from './adminAuth.js';
import { blockCatalog, publishedPage } from './catalog.js';
import { adminPages, ApiError, sylius } from './sylius.js';

export interface CmsPageData {
	page: CmsPage;
	/** Draft preview by an administrator logged into the builder (?apercu=1). */
	preview: boolean;
	catalog: Catalog;
}

/** Published page (or draft preview), null when it does not exist / is not published. */
export async function loadCmsPage(event: RequestEvent, slug: string): Promise<CmsPageData | null> {
	const { locals, url, cookies } = event;
	const api = sylius(event);
	const preview = url.searchParams.has('apercu') && locals.adminToken !== null;

	let page: CmsPage | null;
	if (preview) {
		try {
			page = await adminPages.get(locals.adminToken, slug);
		} catch (error) {
			if (!(error instanceof ApiError && error.status === 404)) handleAuthError(error, cookies, url);
			page = null;
		}
	} else {
		page = await publishedPage(api, slug);
	}
	if (!page) return null;

	const productCodes = page.blocks.filter((b) => b.type === 'produits').flatMap((b) => parseCodes(b.props.productCodes));
	const taxonCodes = page.blocks.filter((b) => b.type === 'categories').flatMap((b) => parseCodes(b.props.taxonCodes));

	return {
		page,
		preview,
		catalog: await blockCatalog(api, locals.viewer, [...new Set(productCodes)], [...new Set(taxonCodes)])
	};
}
