// Checkout of the ECOM cart (#lib/server/checkout.ts): /commande/adresse → livraison → paiement →
// recapitulatif → confirmation. Every step re-checks the cart (visitor → login, PRO / empty cart →
// /panier, step not reached → furthest reachable step) inside the queries.
import { invalid, redirect } from '@sveltejs/kit';
import { command, form, getRequestEvent, query } from '$app/server';
import * as v from 'valibot';
import { CHECKOUT_STEPS } from '#lib/order/checkout.js';
import {
	ADDRESS_FIELDS,
	addressStep,
	checkoutView,
	choosePayment,
	chooseShipping,
	completeOrder,
	confirmedOrder,
	paymentStep,
	saveAddresses,
	shippingStep
} from '#lib/server/checkout.js';
import { EMPTY_CART } from '#lib/server/cart.js';
import { ApiError } from '#lib/server/sylius.js';
import { getCartSummary } from './cart.remote.js';

// Step forms end with a redirect. They refresh (or set) the query the next page needs: a redirect
// without explicit refresh makes the client refresh every query of the page it leaves, and the
// tunnel layout's `getCheckout(step)` of that page would then bounce (e.g. to /panier once the
// order is placed) before the next page shows.

/** API refusal (4xx) → message shown in the form; anything else (redirect, 5xx) goes on. */
function rethrow(error: unknown): never {
	if (error instanceof ApiError && error.status < 500) invalid(error.message);
	throw error;
}

/** Cart being ordered (order + display lines) for a step: summary of the tunnel layout, recap page. */
export const getCheckout = query(v.picklist(CHECKOUT_STEPS.map((step) => step.name)), (step) => checkoutView(getRequestEvent(), step));

// --- Adresse -------------------------------------------------------------------------------------

/** Address book, countries and pre-filled shipping / billing sections. */
export const getAddressStep = query(() => addressStep(getRequestEvent()));

const address = v.object({
	choice: v.optional(v.string(), 'new'),
	...Object.fromEntries(ADDRESS_FIELDS.map((field) => [field, v.optional(v.string(), '')])) as Record<
		(typeof ADDRESS_FIELDS)[number],
		v.OptionalSchema<v.StringSchema<undefined>, ''>
	>
});

/**
 * PUT /shop/orders/{token} (checkoutState → addressed). `shipping.*` / `billing.*`: `choice` (saved
 * address id or "new") and the typed fields; `billing` is ignored when `sameBilling` is checked.
 */
export const submitAddresses = form(
	v.object({ shipping: address, billing: v.optional(address), sameBilling: v.optional(v.boolean(), false) }),
	async ({ shipping, billing, sameBilling }) => {
		const event = getRequestEvent();
		let message: string | null;
		try {
			message = await saveAddresses(event, shipping, sameBilling ? null : (billing ?? { ...shipping, choice: 'new' }));
		} catch (error) {
			rethrow(error);
		}
		if (message) invalid(message);
		await getCheckout('select_shipping').refresh();
		redirect(303, '/commande/livraison');
	}
);

// --- Livraison -----------------------------------------------------------------------------------

/** Carriers / Yack agencies offered for the shipment and the selected one. */
export const getShippingStep = query(() => shippingStep(getRequestEvent()));

/** PATCH /shop/orders/{token}/shipments/{id} (checkoutState → shipping_selected). */
export const submitShipping = form(
	v.object({ shippingMethod: v.pipe(v.string('Choisissez un mode de livraison.'), v.nonEmpty('Choisissez un mode de livraison.')) }),
	async ({ shippingMethod }) => {
		try {
			await chooseShipping(getRequestEvent(), shippingMethod);
		} catch (error) {
			rethrow(error);
		}
		await getCheckout('select_payment').refresh();
		redirect(303, '/commande/paiement');
	}
);

// --- Paiement ------------------------------------------------------------------------------------

/** Payment methods (with their escompte) and the selected one. */
export const getPaymentStep = query(() => paymentStep(getRequestEvent()));

const paymentMethod = v.pipe(v.string('Choisissez un mode de paiement.'), v.nonEmpty('Choisissez un mode de paiement.'));

/**
 * Payment method picked (JS, on change): saved at once, the summary of the layout (escompte, VAT,
 * total) comes back in the same response.
 */
export const selectPaymentMethod = command(paymentMethod, async (method) => {
	try {
		await choosePayment(getRequestEvent(), method);
	} catch (error) {
		if (error instanceof ApiError && error.status < 500) return { error: error.message };
		throw error;
	}
	await Promise.all([getCheckout('select_payment').refresh(), getPaymentStep().refresh()]);
	return { error: null };
});

/**
 * Payment step form. `action` = submit button: "next" (checkoutState → payment_selected, then the
 * recap) or "select" (no JS: stay on the step with the summary recomputed).
 */
export const submitPayment = form(
	v.object({ paymentMethod, action: v.optional(v.picklist(['select', 'next']), 'next') }),
	async ({ paymentMethod, action }) => {
		try {
			await choosePayment(getRequestEvent(), paymentMethod);
		} catch (error) {
			rethrow(error);
		}
		if (action === 'next') {
			await getCheckout('complete').refresh();
			redirect(303, '/commande/recapitulatif');
		}
		await Promise.all([getCheckout('select_payment').refresh(), getPaymentStep().refresh()]);
		return { selected: paymentMethod };
	}
);

// --- Récapitulatif / confirmation ----------------------------------------------------------------

const CGV = 'Acceptez les conditions générales de vente pour valider la commande.';

/**
 * PATCH /shop/orders/{token}/complete: order placed, confirmation e-mail sent by Sylius, cart cookie
 * dropped, header count emptied in the same response, then the confirmation page.
 */
export const placeOrder = form(
	v.object({ notes: v.optional(v.pipe(v.string(), v.trim(), v.maxLength(1000, 'Commentaire limité à 1000 caractères.')), ''), cgv: v.optional(v.boolean(), false) }),
	async ({ notes, cgv }) => {
		if (!cgv) invalid(CGV);
		let token: string;
		try {
			token = await completeOrder(getRequestEvent(), notes);
		} catch (error) {
			rethrow(error);
		}
		getCartSummary().set(EMPTY_CART);
		redirect(303, `/commande/confirmation/${token}`);
	}
);

/** Placed order of the confirmation page (number, payment instructions, summary). */
export const getConfirmedOrder = query(v.string(), (token) => confirmedOrder(getRequestEvent(), token));
