// Public CMS area: published pages (footer, menu), page content with the products / taxons of its blocks.
import { error, redirect } from '@sveltejs/kit';
import { getRequestEvent, query } from '$app/server';
import * as v from 'valibot';
import type { CmsPageSummary } from '#lib/blocks/types.js';
import { publishedPages } from '#lib/server/catalog.js';
import { loadCmsPage, type CmsPageData } from '#lib/server/cms.js';
import { sylius } from '#lib/server/sylius.js';

/** Homepage slug: its CMS page lives at "/". */
const HOME = 'accueil';

/** `preview`: ?apercu in the URL; honoured only for an administrator logged into the builder. */
const PageArgs = v.object({ slug: v.string(), preview: v.optional(v.boolean(), false) });

/** Published CMS pages (footer links, "Nos services" menu entry). */
export const getPublishedPages = query((): Promise<CmsPageSummary[]> => publishedPages(sylius(getRequestEvent())));

/** Homepage CMS page, null while "accueil" is not published (the page shows its static hero). */
export const getHomePage = query(v.optional(v.boolean(), false), (preview): Promise<CmsPageData | null> =>
	loadCmsPage(getRequestEvent(), HOME, preview)
);

/** CMS page /[slug]: 404 when it does not exist / is not published; /accueil → /. */
export const getCmsPage = query(PageArgs, async ({ slug, preview }): Promise<CmsPageData> => {
	if (slug === HOME) redirect(308, preview ? '/?apercu=1' : '/');
	const data = await loadCmsPage(getRequestEvent(), slug, preview);
	if (!data) error(404, 'Page introuvable');
	return data;
});
