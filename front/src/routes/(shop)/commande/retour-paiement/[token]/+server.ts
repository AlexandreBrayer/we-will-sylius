import { error, redirect } from '@sveltejs/kit';
import { concludeCardPayment } from '#lib/server/payment.js';
import { loginUrl } from '#lib/server/session.js';
import { ApiError } from '#lib/server/sylius.js';
import type { RequestHandler } from './$types';

/**
 * Return URL given to the payment provider: the outcome of the card session is applied to the order
 * (Sylius), then the confirmation page when paid, otherwise the payment page of the order
 * ("Paiement non abouti": retry by card or switch to a bank transfer).
 */
export const GET: RequestHandler = async (event) => {
	if (!event.locals.viewer.authenticated) redirect(303, loginUrl(event.url));
	const token = event.params.token;
	let accepted = false;
	try {
		accepted = (await concludeCardPayment(event, token)).status === 'accepted';
	} catch (e) {
		if (e instanceof ApiError && e.status === 404) error(404, 'Commande introuvable');
		// 409: no session in progress (already concluded, or paid): the payment page decides.
		if (!(e instanceof ApiError) || e.status !== 409) throw e;
	}
	redirect(303, `/commande/${accepted ? 'confirmation' : 'reglement'}/${encodeURIComponent(token)}`);
};
