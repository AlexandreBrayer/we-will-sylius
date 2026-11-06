import { fail } from '@sveltejs/kit';
import type { Customer } from '#lib/account/types.js';
import { requireCustomer } from '#lib/server/account.js';
import { ApiError, sylius } from '#lib/server/sylius.js';
import type { SavedAddress } from '#lib/order/types.js';
import type { Actions, PageServerLoad } from './$types';

/** Address book: GET /shop/addresses + the default one (customer.defaultAddress, used for billing). */
export const load: PageServerLoad = async (event) => {
	requireCustomer(event);
	const api = sylius(event);
	const [addresses, customer] = await Promise.all([
		api.get<SavedAddress[]>('/addresses'),
		api.get<Customer>(`/customers/${event.locals.viewer.customerId}`)
	]);
	const defaultId = customer.defaultAddress ? Number(customer.defaultAddress.split('/').pop()) : null;
	return {
		defaultAddress: addresses.find((address) => address.id === defaultId) ?? null,
		addresses: addresses.filter((address) => address.id !== defaultId),
		saved: event.url.searchParams.get('enregistree') === '1'
	};
};

function addressId(form: FormData): number | null {
	const id = Number(form.get('id'));
	return Number.isInteger(id) && id > 0 ? id : null;
}

export const actions: Actions = {
	/** PUT /shop/customers/{id} {defaultAddress}. */
	parDefaut: async (event) => {
		requireCustomer(event);
		const id = addressId(await event.request.formData());
		if (id === null) return fail(400, { error: 'Adresse inconnue.' });
		await sylius(event).put(`/customers/${event.locals.viewer.customerId}`, { defaultAddress: `/api/v2/shop/addresses/${id}` });
		return { message: 'Adresse par défaut mise à jour.' };
	},

	/** DELETE /shop/addresses/{id}. */
	supprimer: async (event) => {
		requireCustomer(event);
		const id = addressId(await event.request.formData());
		if (id === null) return fail(400, { error: 'Adresse inconnue.' });
		try {
			await sylius(event).delete(`/addresses/${id}`);
		} catch (error) {
			if (error instanceof ApiError && error.status < 500) return fail(error.status, { error: error.message });
			throw error;
		}
		return { message: 'Adresse supprimée.' };
	}
};
