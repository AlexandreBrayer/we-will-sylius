import { fail, redirect } from '@sveltejs/kit';
import { TOKEN_COOKIE } from '$lib/server/auth.js';
import { ApiError, login } from '$lib/server/sylius.js';
import type { Actions } from './$types';

/** Builder login = Sylius admin API login (JWT kept in an httpOnly cookie). */
export const actions: Actions = {
	default: async ({ request, cookies, url }) => {
		const form = await request.formData();
		const email = String(form.get('email') ?? '').trim();
		const password = String(form.get('password') ?? '');
		if (!email || !password) return fail(400, { email, error: 'Saisissez votre e-mail et votre mot de passe.' });

		try {
			const { token, expiresAt } = await login(email, password);
			cookies.set(TOKEN_COOKIE, token, {
				path: '/',
				httpOnly: true,
				sameSite: 'lax',
				secure: url.protocol === 'https:',
				maxAge: Math.max(60, expiresAt - Math.floor(Date.now() / 1000))
			});
		} catch (error) {
			if (error instanceof ApiError) return fail(401, { email, error: error.message });
			return fail(502, { email, error: 'Sylius est injoignable.' });
		}

		const target = url.searchParams.get('redirect') ?? '';
		redirect(303, target.startsWith('/') && !target.startsWith('//') ? target : '/admin/builder');
	}
};
