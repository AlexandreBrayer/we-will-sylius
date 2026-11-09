import { redirect } from '@sveltejs/kit';
import { safeRedirect } from '#lib/server/session.js';
import type { PageServerLoad } from './$types';

/** Guard only (the form is `login`, #lib/remote/session.remote.ts): a customer already logged in goes on. */
export const load: PageServerLoad = ({ locals, url }) => {
	if (locals.viewer.authenticated) redirect(303, safeRedirect(url.searchParams.get('redirect')));
};
