import { error } from '@sveltejs/kit';
import { parseCodes } from '$lib/blocks/parse.js';
import type { CmsPage } from '$lib/blocks/types.js';
import { handleAuthError } from '$lib/server/auth.js';
import { adminPages, ApiError, catalog, publishedPage } from '$lib/server/sylius.js';
import type { PageServerLoad } from './$types';

export const load: PageServerLoad = async ({ params, url, locals, cookies }) => {
	// ?apercu=1 : draft preview for a logged-in administrator (builder session).
	const preview = url.searchParams.has('apercu') && locals.adminToken !== null;

	let page: CmsPage | null;
	try {
		page = preview ? await adminPages.get(locals.adminToken, params.slug) : await publishedPage(params.slug);
	} catch (e) {
		if (e instanceof ApiError && e.status === 404) page = null;
		else page = handleAuthError(e, cookies, url);
	}
	if (!page) error(404, 'Page introuvable');

	const productCodes = page.blocks.filter((b) => b.type === 'produits').flatMap((b) => parseCodes(b.props.productCodes));
	const taxonCodes = page.blocks.filter((b) => b.type === 'categories').flatMap((b) => parseCodes(b.props.taxonCodes));

	return {
		page,
		preview,
		catalog: await catalog([...new Set(productCodes)], [...new Set(taxonCodes)])
	};
};
