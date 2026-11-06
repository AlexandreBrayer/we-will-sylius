import { fail } from '@sveltejs/kit';
import { fieldErrors } from '#lib/server/account.js';
import { ApiError, sylius } from '#lib/server/sylius.js';
import type { Actions } from './$types';

export const actions: Actions = {
	/** Link of the "mot de passe oublié" e-mail: PATCH /shop/customers/reset-password/{token} (202). */
	default: async (event) => {
		const form = await event.request.formData();
		const newPassword = String(form.get('newPassword') ?? '');
		const confirmNewPassword = String(form.get('confirmNewPassword') ?? '');
		const errors: Record<string, string> = {};
		if (newPassword.length < 8) errors.newPassword = 'Le mot de passe doit comporter au moins 8 caractères.';
		else if (newPassword !== confirmNewPassword) errors.confirmNewPassword = 'Les deux mots de passe ne correspondent pas.';
		if (Object.keys(errors).length > 0) return fail(400, { errors, error: null });

		try {
			await sylius(event).patch(`/customers/reset-password/${encodeURIComponent(event.params.token)}`, { newPassword, confirmNewPassword });
		} catch (error) {
			if (!(error instanceof ApiError) || error.status >= 500) throw error;
			if (error.status === 422 && error.violations.some((v) => ['newPassword', 'confirmNewPassword'].includes(v.propertyPath))) {
				return fail(422, { errors: fieldErrors(error.violations), error: null });
			}
			return fail(error.status, {
				errors: {},
				error: 'Ce lien de réinitialisation est invalide ou a expiré : demandez-en un nouveau.'
			});
		}
		return { reset: true };
	}
};
