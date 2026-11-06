import { fail, redirect } from '@sveltejs/kit';
import type { Country } from '#lib/account/types.js';
import {
	STEP_COUNT,
	firstInvalidStep,
	readValues,
	registrationPayload,
	violationErrors,
	type FieldErrors,
	type RegistrationValues
} from '#lib/account/registration.js';
import { REGISTERED_COOKIE } from '#lib/server/account.js';
import { ApiError, sylius } from '#lib/server/sylius.js';
import type { Actions, PageServerLoad } from './$types';

export const load: PageServerLoad = async (event) => {
	if (event.locals.viewer.authenticated) redirect(303, '/compte');
	return { countries: await sylius(event).get<Country[]>('/countries') };
};

interface StepResult {
	values: RegistrationValues;
	step: number;
	errors: FieldErrors;
	error?: string;
}

/** Step the wizard was on when submitted (hidden field `step`). */
function submittedStep(form: FormData): number {
	const step = Number(form.get('step'));
	return Number.isInteger(step) && step >= 1 && step <= STEP_COUNT ? step : 1;
}

// Without JavaScript every step is a POST carrying all the fields (hidden steps included);
// with JavaScript the steps change in the browser and only `creer` is posted.
export const actions: Actions = {
	/** "Continuer": validates the current step (and the previous ones), shows the next one. */
	etape: async ({ request }) => {
		const form = await request.formData();
		const values = readValues(form);
		const step = submittedStep(form);
		const invalid = firstInvalidStep(values, step);
		if (invalid) return fail(400, { values, ...invalid } satisfies StepResult);
		return { values, step: Math.min(step + 1, STEP_COUNT), errors: {} } satisfies StepResult;
	},

	/** "‹ Étape précédente". */
	precedent: async ({ request }) => {
		const form = await request.formData();
		return { values: readValues(form), step: Math.max(submittedStep(form) - 1, 1), errors: {} } satisfies StepResult;
	},

	/** "Créer mon compte": POST /shop/yack/registrations, then the "check your e-mails" page. */
	creer: async (event) => {
		const form = await event.request.formData();
		const values = readValues(form);
		const invalid = firstInvalidStep(values);
		if (invalid) return fail(400, { values, ...invalid } satisfies StepResult);

		try {
			await sylius(event).post('/yack/registrations', registrationPayload(values));
		} catch (error) {
			if (!(error instanceof ApiError) || error.status >= 500) throw error;
			if (error.status === 422 && error.violations.length > 0) {
				const { step, errors, global } = violationErrors(error.violations);
				return fail(422, { values, step, errors, error: global.join(' ') || undefined } satisfies StepResult);
			}
			return fail(error.status, { values, step: STEP_COUNT, errors: {}, error: error.message } satisfies StepResult);
		}

		event.cookies.set(REGISTERED_COOKIE, JSON.stringify({ email: values.email, pro: values.profile === 'pro' }), {
			path: '/inscription',
			httpOnly: true,
			sameSite: 'lax',
			secure: event.url.protocol === 'https:',
			maxAge: 60 * 30
		});
		redirect(303, '/inscription/terminee');
	}
};
