import { error, redirect } from '@sveltejs/kit';
import { loadCmsPage } from '#lib/server/cms.js';
import type { PageServerLoad } from './$types';

export const load: PageServerLoad = async (event) => {
	// The homepage CMS page lives at "/".
	if (event.params.slug === 'accueil') redirect(308, `/${event.url.search}`);

	const data = await loadCmsPage(event, event.params.slug);
	if (!data) error(404, 'Page introuvable');

	return data;
};
