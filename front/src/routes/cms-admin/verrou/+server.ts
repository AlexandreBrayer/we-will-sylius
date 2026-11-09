// Release of the edit lock when the builder tab closes (D13): `navigator.sendBeacon` at `pagehide` posts
// `{target, slug | id, token}` here (text body: what a beacon sends), and this route gives the lock back
// with the builder JWT (POST …/lock {action: release}). Always 204: a beacon reads no answer, and a lock
// that is not given back is free anyway after 2 minutes without a sign of life.
import type { RequestHandler } from './$types';
import { request } from '#lib/server/sylius.js';

export const POST: RequestHandler = async ({ request: incoming, locals }) => {
	const token = locals.adminToken;
	let body: unknown = null;
	try {
		body = JSON.parse(await incoming.text());
	} catch {
		body = null;
	}
	const release = typeof body === 'object' && body !== null ? (body as Record<string, unknown>) : {};
	const lockToken = typeof release.token === 'string' && /^[0-9a-f]{32}$/.test(release.token) ? release.token : null;
	const path =
		release.target === 'page' && typeof release.slug === 'string' && /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(release.slug)
			? `/admin/cms-pages/${release.slug}/lock`
			: release.target === 'template' && typeof release.id === 'number' && Number.isInteger(release.id) && release.id > 0
				? `/admin/cms-templates/${release.id}/lock`
				: null;
	if (token && lockToken && path) {
		await request(path, { method: 'POST', token, body: { action: 'release', token: lockToken } }).catch(() => null);
	}
	return new Response(null, { status: 204 });
};
