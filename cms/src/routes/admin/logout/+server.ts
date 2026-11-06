import { redirect } from '@sveltejs/kit';
import { TOKEN_COOKIE } from '$lib/server/auth.js';
import type { RequestHandler } from './$types';

export const POST: RequestHandler = ({ cookies }) => {
	cookies.delete(TOKEN_COOKIE, { path: '/' });
	redirect(303, '/admin/login');
};
