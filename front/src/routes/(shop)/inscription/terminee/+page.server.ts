import { redirect } from '@sveltejs/kit';
import { REGISTERED_COOKIE } from '#lib/server/account.js';
import type { PageServerLoad } from './$types';

/** "Check your e-mails" page, reached right after the sign-up (cookie set by ?/creer). */
export const load: PageServerLoad = ({ cookies }) => {
	try {
		const { email, pro } = JSON.parse(cookies.get(REGISTERED_COOKIE) ?? '') as { email: string; pro: boolean };
		return { email: String(email), pro: pro === true };
	} catch {
		redirect(303, '/inscription');
	}
};
