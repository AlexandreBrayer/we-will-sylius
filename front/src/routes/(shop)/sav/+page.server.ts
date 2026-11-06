import { redirect } from '@sveltejs/kit';
import { savUrl } from '#lib/sav/types.js';
import type { PageServerLoad } from './$types';

/** Search form (GET ?serial=…) → /sav/{serial}, which shows the warranty and the models. */
export const load: PageServerLoad = ({ url }) => {
	const query = url.searchParams.get('serial')?.trim();
	if (query) redirect(303, savUrl(query));
	return { breadcrumb: [{ label: 'Accueil', url: '/' }, { label: 'SAV', url: null }] };
};
