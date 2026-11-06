import { error, fail, redirect } from '@sveltejs/kit';
import type { Country } from '#lib/account/types.js';
import { fieldErrors, readAddress, requireCustomer } from '#lib/server/account.js';
import { ApiError, sylius } from '#lib/server/sylius.js';
import type { SavedAddress } from '#lib/order/types.js';
import type { Actions, PageServerLoad } from './$types';

export const load: PageServerLoad = async (event) => {
	requireCustomer(event);
	const api = sylius(event);
	const [address, countries] = await Promise.all([
		/^\d+$/.test(event.params.id) ? api.find<SavedAddress>(`/addresses/${event.params.id}`) : null,
		api.get<Country[]>('/countries')
	]);
	if (!address) error(404, 'Adresse introuvable');
	return { address, countries };
};

export const actions: Actions = {
	/** PUT /shop/addresses/{id}. */
	default: async (event) => {
		requireCustomer(event);
		const address = readAddress(await event.request.formData());
		try {
			await sylius(event).put(`/addresses/${encodeURIComponent(event.params.id)}`, address);
		} catch (err) {
			if (err instanceof ApiError && err.status === 422) return fail(422, { address, errors: fieldErrors(err.violations) });
			if (err instanceof ApiError && err.status === 404) error(404, 'Adresse introuvable');
			throw err;
		}
		redirect(303, '/compte/adresses?enregistree=1');
	}
};
