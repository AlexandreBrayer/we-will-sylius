import { fail, redirect } from '@sveltejs/kit';
import type { Country } from '#lib/account/types.js';
import { fieldErrors, readAddress, requireCustomer } from '#lib/server/account.js';
import { ApiError, sylius } from '#lib/server/sylius.js';
import type { Actions, PageServerLoad } from './$types';

export const load: PageServerLoad = async (event) => {
	requireCustomer(event);
	return { countries: await sylius(event).get<Country[]>('/countries') };
};

export const actions: Actions = {
	/** POST /shop/addresses. */
	default: async (event) => {
		requireCustomer(event);
		const address = readAddress(await event.request.formData());
		try {
			await sylius(event).post('/addresses', address);
		} catch (error) {
			if (error instanceof ApiError && error.status === 422) return fail(422, { address, errors: fieldErrors(error.violations) });
			throw error;
		}
		redirect(303, '/compte/adresses?enregistree=1');
	}
};
