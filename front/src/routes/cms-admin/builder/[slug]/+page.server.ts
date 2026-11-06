import { error } from '@sveltejs/kit';
import { handleAuthError } from '#lib/server/adminAuth.js';
import { adminPages, ApiError } from '#lib/server/sylius.js';
import type { PageServerLoad } from './$types';

export const load: PageServerLoad = async ({ params, locals, cookies, url }) => {
	try {
		return { page: await adminPages.get(locals.adminToken, params.slug) };
	} catch (e) {
		if (e instanceof ApiError && e.status === 404) error(404, 'Page introuvable');
		return handleAuthError(e, cookies, url);
	}
};
