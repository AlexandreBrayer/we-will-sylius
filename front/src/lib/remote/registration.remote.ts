// Anonymous account flows: 4-step sign-up (/inscription), links of the e-mails (activation, new password)
// and "mot de passe oublié". Step rules and API mapping: #lib/account/registration.ts.
import { invalid, redirect } from '@sveltejs/kit';
import { form, getRequestEvent, query } from '$app/server';
import * as v from 'valibot';
import type { Country } from '#lib/account/types.js';
import { STEP_COUNT, VIOLATION_FIELDS, firstInvalidStep, registrationPayload, toValues, type RegistrationValues } from '#lib/account/registration.js';
import { REGISTERED_COOKIE, violationIssues } from '#lib/server/account.js';
import { ApiError, sylius } from '#lib/server/sylius.js';

const text = v.optional(v.string(), '');

/** /inscription: countries of the address step; a customer already logged in goes to /compte. */
export const getRegistrationForm = query(async (): Promise<{ countries: Country[] }> => {
	const event = getRequestEvent();
	if (event.locals.viewer.authenticated) redirect(303, '/compte');
	return { countries: await sylius(event).get<Country[]>('/countries') };
});

/**
 * Sign-up wizard. All the fields stay in one form (hidden steps included). With JavaScript the steps
 * change in the browser and only `intent=creer` reaches the server; without it every button posts:
 * `etape` validates the steps up to `step` and answers the next one, `precedent` the previous one
 * (result `{ step, values }`), `creer` validates everything and calls POST /shop/yack/registrations.
 * Errors are field issues (the page shows the earliest step holding one); API 422 violations are mapped
 * to the fields, the others are issues of the whole form.
 */
export const register = form(
	v.object({
		intent: v.optional(v.picklist(['etape', 'precedent', 'creer']), 'etape'),
		step: v.optional(v.string(), '1'),
		profile: text,
		companyName: text,
		siret: text,
		vatNumber: text,
		lastName: text,
		firstName: text,
		email: text,
		phone: text,
		password: text,
		passwordConfirm: text,
		billingStreet: text,
		billingPostcode: text,
		billingCity: text,
		billingCountryCode: text,
		shippingSame: v.optional(v.boolean(), false),
		shippingStreet: text,
		shippingPostcode: text,
		shippingCity: text,
		shippingCountryCode: text
	}),
	async ({ intent, step: posted, ...input }): Promise<{ step: number; values: RegistrationValues }> => {
		const event = getRequestEvent();
		if (event.locals.viewer.authenticated) redirect(303, '/compte');

		const values = toValues(input);
		const step = Math.min(Math.max(Number.parseInt(posted, 10) || 1, 1), STEP_COUNT);
		if (intent === 'precedent') return { step: Math.max(step - 1, 1), values };

		const wrong = firstInvalidStep(values, intent === 'creer' ? STEP_COUNT : step);
		if (wrong) invalid(...Object.entries(wrong.errors).map(([field, message]) => ({ message, path: [field] })));
		if (intent === 'etape') return { step: Math.min(step + 1, STEP_COUNT), values };

		try {
			await sylius(event).post('/yack/registrations', registrationPayload(values));
		} catch (error) {
			if (!(error instanceof ApiError) || error.status >= 500) throw error;
			invalid(...(error.status === 422 && error.violations.length > 0 ? violationIssues(error.violations, VIOLATION_FIELDS) : [error.message]));
		}

		event.cookies.set(REGISTERED_COOKIE, JSON.stringify({ email: values.email, pro: values.profile === 'pro' }), {
			path: '/',
			httpOnly: true,
			sameSite: 'lax',
			secure: event.url.protocol === 'https:',
			maxAge: 60 * 30
		});
		redirect(303, '/inscription/terminee');
	}
);

/** /inscription/terminee ("check your e-mails"): who just signed up (cookie set by `register`), else back to /inscription. */
export const getRegistered = query((): { email: string; pro: boolean } => {
	let registered: unknown = null;
	try {
		registered = JSON.parse(getRequestEvent().cookies.get(REGISTERED_COOKIE) ?? '');
	} catch {
		// no or broken cookie
	}
	if (typeof registered !== 'object' || registered === null || !('email' in registered) || typeof registered.email !== 'string') {
		redirect(303, '/inscription');
	}
	return { email: registered.email, pro: 'pro' in registered && registered.pro === true };
});

/**
 * Link of the activation e-mail: POST /shop/yack/activations/{token} (204, 404 when invalid / used).
 * A query because the link is a GET page that must work without JS (the former page load).
 */
export const activateAccount = query(v.string(), async (token): Promise<{ activated: boolean; error: string | null }> => {
	try {
		await sylius(getRequestEvent()).post(`/yack/activations/${encodeURIComponent(token)}`);
		return { activated: true, error: null };
	} catch (error) {
		if (!(error instanceof ApiError) || error.status >= 500) throw error;
		return { activated: false, error: error.status === 404 ? "Lien d'activation invalide ou déjà utilisé." : error.message };
	}
});

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/**
 * "Mot de passe oublié": POST /shop/customers/reset-password (202), e-mail with
 * {FRONT_URL}/mot-de-passe/reinitialiser/{token}. Result `{ sent }` (the e-mail).
 */
export const requestPasswordReset = form(
	v.object({ email: v.pipe(v.optional(v.string(), ''), v.trim(), v.regex(EMAIL, 'Saisissez une adresse e-mail valide.')) }),
	async ({ email }) => {
		try {
			await sylius(getRequestEvent()).post('/customers/reset-password', { email });
		} catch (error) {
			// Unknown e-mail: same answer, the form must not reveal which accounts exist.
			if (!(error instanceof ApiError) || error.status >= 500) throw error;
			if (error.status === 422) invalid(error.message);
		}
		return { sent: email };
	}
);

/**
 * Link of the "mot de passe oublié" e-mail: PATCH /shop/customers/reset-password/{token} (202). Result `{ reset }`.
 * "_" fields are never sent back to the page.
 */
export const resetPassword = form(
	v.pipe(
		v.object({
			token: text,
			_newPassword: v.pipe(text, v.minLength(8, 'Le mot de passe doit comporter au moins 8 caractères.')),
			_confirmNewPassword: text
		}),
		v.forward(
			v.partialCheck(
				[['_newPassword'], ['_confirmNewPassword']],
				(input) => input._newPassword === input._confirmNewPassword,
				'Les deux mots de passe ne correspondent pas.'
			),
			['_confirmNewPassword']
		)
	),
	async ({ token, _newPassword, _confirmNewPassword }) => {
		try {
			await sylius(getRequestEvent()).patch(`/customers/reset-password/${encodeURIComponent(token)}`, {
				newPassword: _newPassword,
				confirmNewPassword: _confirmNewPassword
			});
		} catch (error) {
			if (!(error instanceof ApiError) || error.status >= 500) throw error;
			const fields = error.status === 422 ? violationIssues(error.violations, { newPassword: '_newPassword', confirmNewPassword: '_confirmNewPassword' }) : [];
			const onFields = fields.filter((issue) => issue.path);
			invalid(...(onFields.length > 0 ? onFields : ['Ce lien de réinitialisation est invalide ou a expiré : demandez-en un nouveau.']));
		}
		return { reset: true };
	}
);
