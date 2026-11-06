import { fail } from '@sveltejs/kit';
import type { Customer } from '#lib/account/types.js';
import { fieldErrors, requireCustomer } from '#lib/server/account.js';
import { ApiError, sylius } from '#lib/server/sylius.js';
import type { Actions, PageServerLoad } from './$types';

export const load: PageServerLoad = async (event) => {
	requireCustomer(event);
	return { customer: await sylius(event).get<Customer>(`/customers/${event.locals.viewer.customerId}`) };
};

const PHONE = /^\+?[0-9 .()-]{10,20}$/;

export const actions: Actions = {
	/**
	 * PUT /shop/customers/{id}: name and phone. The e-mail is the login (and the JWT subject):
	 * it is shown, not edited, so the session stays valid.
	 */
	infos: async (event) => {
		requireCustomer(event);
		const form = await event.request.formData();
		const values = {
			firstName: String(form.get('firstName') ?? '').trim(),
			lastName: String(form.get('lastName') ?? '').trim(),
			phoneNumber: String(form.get('phoneNumber') ?? '').trim()
		};
		const errors: Record<string, string> = {};
		if (!values.firstName) errors.firstName = 'Indiquez votre prénom.';
		if (!values.lastName) errors.lastName = 'Indiquez votre nom.';
		if (values.phoneNumber && !PHONE.test(values.phoneNumber)) errors.phoneNumber = 'Numéro de téléphone invalide (10 chiffres attendus).';
		if (Object.keys(errors).length > 0) return fail(400, { infos: { values, errors } });

		try {
			await sylius(event).put(`/customers/${event.locals.viewer.customerId}`, { ...values, phoneNumber: values.phoneNumber || null });
		} catch (error) {
			if (error instanceof ApiError && error.status === 422) return fail(422, { infos: { values, errors: fieldErrors(error.violations) } });
			throw error;
		}
		return { infos: { values, errors: {}, saved: true } };
	},

	/** PUT /shop/customers/{id}/password. */
	motDePasse: async (event) => {
		requireCustomer(event);
		const form = await event.request.formData();
		const body = {
			currentPassword: String(form.get('currentPassword') ?? ''),
			newPassword: String(form.get('newPassword') ?? ''),
			confirmNewPassword: String(form.get('confirmNewPassword') ?? '')
		};
		const errors: Record<string, string> = {};
		if (!body.currentPassword) errors.currentPassword = 'Saisissez votre mot de passe actuel.';
		if (body.newPassword.length < 8) errors.newPassword = 'Le mot de passe doit comporter au moins 8 caractères.';
		else if (body.newPassword !== body.confirmNewPassword) errors.confirmNewPassword = 'Les deux mots de passe ne correspondent pas.';
		if (Object.keys(errors).length > 0) return fail(400, { password: { errors } });

		try {
			await sylius(event).put(`/customers/${event.locals.viewer.customerId}/password`, body);
		} catch (error) {
			if (error instanceof ApiError && error.status === 422) return fail(422, { password: { errors: fieldErrors(error.violations) } });
			throw error;
		}
		return { password: { errors: {}, saved: true } };
	}
};
