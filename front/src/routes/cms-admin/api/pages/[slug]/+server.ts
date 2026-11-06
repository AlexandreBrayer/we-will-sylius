import { adminPages, AdminAuthError, ApiError } from '#lib/server/sylius.js';
import type { CmsPage } from '#lib/blocks/types.js';
import type { RequestHandler } from './$types';

/** Builder save (draft / publish): proxied to the Sylius admin API with the session JWT. */
export const PUT: RequestHandler = async ({ params, request, locals }) => {
	const body = (await request.json()) as Partial<CmsPage>;
	try {
		const page = await adminPages.update(locals.adminToken, params.slug, {
			title: body.title,
			metaTitle: body.metaTitle ?? null,
			metaDescription: body.metaDescription ?? null,
			status: body.status,
			blocks: body.blocks,
			grapesData: body.grapesData ?? null
		});
		return Response.json(page);
	} catch (e) {
		if (e instanceof AdminAuthError) return Response.json({ message: 'Session expirée : reconnectez-vous.' }, { status: 401 });
		if (e instanceof ApiError) return Response.json({ message: e.message }, { status: e.status });
		throw e;
	}
};
