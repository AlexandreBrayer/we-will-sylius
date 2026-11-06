import { redirect } from '@sveltejs/kit';
import { endSession } from '#lib/server/session.js';
import type { RequestHandler } from './$types';

/**
 * Logout (form POST from the header; SvelteKit checks the Origin of form posts). The shop JWT
 * is stateless: dropping the cookies ends the session. GET only sends back to the homepage.
 */
export const POST: RequestHandler = ({ cookies }) => {
	endSession(cookies);
	redirect(303, '/');
};

export const GET: RequestHandler = () => redirect(303, '/');
