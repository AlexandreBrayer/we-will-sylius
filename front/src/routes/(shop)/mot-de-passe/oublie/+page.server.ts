import { fail } from '@sveltejs/kit';
import { ApiError, sylius } from '#lib/server/sylius.js';
import type { Actions } from './$types';

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export const actions: Actions = {
	/** POST /shop/customers/reset-password (202): e-mail with {FRONT_URL}/mot-de-passe/reinitialiser/{token}. */
	default: async (event) => {
		const email = String((await event.request.formData()).get('email') ?? '').trim();
		if (!EMAIL.test(email)) return fail(400, { email, error: 'Saisissez une adresse e-mail valide.' });

		try {
			await sylius(event).post('/customers/reset-password', { email });
		} catch (error) {
			// Unknown e-mail: same answer, the form must not reveal which accounts exist.
			if (!(error instanceof ApiError) || error.status >= 500) throw error;
			if (error.status === 422) return fail(422, { email, error: error.message });
		}
		return { sent: email };
	}
};
