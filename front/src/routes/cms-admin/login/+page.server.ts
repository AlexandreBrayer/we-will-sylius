// Former builder login form: the builder opens through the admin session (one login, see
// #lib/server/adminAuth.ts). Kept for bookmarks and old links.
import { redirect } from '@sveltejs/kit';
import { safeRedirect } from '#lib/server/session.js';
import type { PageServerLoad } from './$types';

export const load: PageServerLoad = ({ url }) => {
	redirect(303, `/admin/yack/builder?vers=${encodeURIComponent(safeRedirect(url.searchParams.get('redirect'), '/cms-admin/builder'))}`);
};
