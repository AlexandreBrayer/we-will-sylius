import { fail, redirect } from '@sveltejs/kit';
import { handleAuthError } from '$lib/server/auth.js';
import { adminPages, ApiError } from '$lib/server/sylius.js';
import type { Actions, PageServerLoad } from './$types';

export const load: PageServerLoad = async ({ locals, cookies, url }) => {
	try {
		return { pages: await adminPages.list(locals.adminToken) };
	} catch (e) {
		return handleAuthError(e, cookies, url);
	}
};

export const actions: Actions = {
	create: async ({ request, locals, cookies, url }) => {
		const form = await request.formData();
		const title = String(form.get('title') ?? '').trim();
		const slug = String(form.get('slug') ?? '').trim();
		try {
			await adminPages.create(locals.adminToken, { title, slug });
		} catch (e) {
			if (e instanceof ApiError) return fail(422, { title, slug, error: e.message });
			handleAuthError(e, cookies, url);
		}
		redirect(303, `/admin/builder/${slug}`);
	}
};
