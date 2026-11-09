// Card payment (#lib/server/payment.ts): payment page of a placed order (retry by card, switch to
// a bank transfer), "Mes cartes", and the simulated provider's hosted page /paiement-securise/[session].
import { error, invalid, redirect } from '@sveltejs/kit';
import { form, getRequestEvent, query } from '$app/server';
import * as v from 'valibot';
import { checkCard } from '#lib/payment/card.js';
import type { SimulatedPaymentPage } from '#lib/payment/types.js';
import {
	answerSimulatedChallenge,
	cancelSimulatedPayment,
	deleteSavedCard,
	frontRedirect,
	payableOrder,
	savedCards,
	simulatedPage,
	startCardPayment,
	submitSimulatedCard,
	switchPaymentMethod
} from '#lib/server/payment.js';
import { ApiError } from '#lib/server/sylius.js';

/** API refusal (4xx) → message shown in the form; anything else (redirect, 5xx) goes on. */
function rethrow(e: unknown): never {
	if (e instanceof ApiError && e.status < 500) invalid(e.message);
	throw e;
}

const token = v.pipe(v.string(), v.nonEmpty());

// --- Yack: payment of a placed order --------------------------------------------------------------

/** Order still to pay (`/commande/reglement/[token]`): last refused / cancelled attempt, saved cards, methods. */
export const getPayableOrder = query(v.string(), (orderToken) => payableOrder(getRequestEvent(), orderToken));

/**
 * Pay by card: `savedCard` = id of a saved card (one click) or "new" (card typed on the provider's
 * page). Redirects to the provider (or straight back when no page is needed).
 */
export const payByCard = form(v.object({ token, savedCard: v.optional(v.string(), 'new') }), async ({ token, savedCard }) => {
	let target: string;
	try {
		target = await startCardPayment(getRequestEvent(), token, Number(savedCard) || null);
	} catch (e) {
		rethrow(e);
	}
	redirect(303, target);
});

/** Switch to another method (bank transfer): same order, totals recomputed, then the confirmation page with the RIB. */
export const changePaymentMethod = form(
	v.object({ token, paymentMethod: v.pipe(v.string('Choisissez un mode de paiement.'), v.nonEmpty('Choisissez un mode de paiement.')) }),
	async ({ token, paymentMethod }) => {
		try {
			await switchPaymentMethod(getRequestEvent(), token, paymentMethod);
		} catch (e) {
			rethrow(e);
		}
		redirect(303, `/commande/confirmation/${encodeURIComponent(token)}`);
	}
);

// --- Yack: "Mes cartes" ---------------------------------------------------------------------------

export const getSavedCards = query(() => savedCards(getRequestEvent()));

export const removeSavedCard = form(v.object({ id: v.pipe(v.string(), v.regex(/^\d+$/, 'Carte inconnue.')) }), async ({ id }) => {
	try {
		await deleteSavedCard(getRequestEvent(), Number(id));
	} catch (e) {
		rethrow(e);
	}
	await getSavedCards().refresh();
	return { message: 'Carte supprimée.' };
});

// --- Simulated provider: hosted payment page ------------------------------------------------------

/** Session of the hosted page (merchant, order, amount, step). */
export const getHostedPayment = query(v.string(), (session) => simulatedPage(session));

const session = v.pipe(v.string(), v.regex(/^[a-f0-9]{32}$/, 'Session de paiement inconnue.'));

/** Session over: back to the merchant (Yack return page); otherwise the page shows the next step. */
async function next(page: SimulatedPaymentPage): Promise<void> {
	if (page.step === 'card' || page.step === 'challenge') {
		await getHostedPayment(page.id).refresh();
		return;
	}
	redirect(303, frontRedirect(page.returnUrl, getRequestEvent()));
}

function rethrowSimulator(e: unknown): never {
	if (e instanceof ApiError && e.status === 404) error(404, 'Session de paiement inconnue ou expirée');
	rethrow(e);
}

/**
 * Card typed on the hosted page. `_cardNumber` / `_cvv` start with "_": SvelteKit never sends them
 * back to the browser; they are checked here and dropped, only the masked card goes on.
 */
export const payWithCard = form(
	v.object({
		session,
		_cardNumber: v.optional(v.string(), ''),
		expiry: v.optional(v.string(), ''),
		_cvv: v.optional(v.string(), ''),
		saveCard: v.optional(v.boolean(), false)
	}),
	async ({ session, _cardNumber, expiry, _cvv, saveCard }) => {
		const check = checkCard(_cardNumber, expiry, _cvv, new Date());
		if (!check.ok) {
			const fields = { number: '_cardNumber', expiry: 'expiry', cvv: '_cvv' } as const;
			invalid(...Object.entries(check.errors).map(([field, message]) => ({ message, path: [fields[field as keyof typeof fields]] })));
		}
		let page: SimulatedPaymentPage;
		try {
			page = await submitSimulatedCard(session, check.card, saveCard);
		} catch (e) {
			rethrowSimulator(e);
		}
		await next(page);
	}
);

/** 3-D Secure code typed on the bank page: wrong code = payment refused. */
export const confirmChallenge = form(
	v.object({ session, code: v.pipe(v.string('Saisissez le code reçu par SMS.'), v.trim(), v.nonEmpty('Saisissez le code reçu par SMS.')) }),
	async ({ session, code }) => {
		let page: SimulatedPaymentPage;
		try {
			page = await answerSimulatedChallenge(session, code);
		} catch (e) {
			rethrowSimulator(e);
		}
		await next(page);
	}
);

/** "Annuler et retourner sur le site du marchand". */
export const cancelHostedPayment = form(v.object({ session }), async ({ session }) => {
	let page: SimulatedPaymentPage;
	try {
		page = await cancelSimulatedPayment(session);
	} catch (e) {
		rethrowSimulator(e);
	}
	await next(page);
});
