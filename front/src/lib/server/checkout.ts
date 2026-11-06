// Checkout of the ECOM cart (Sylius flow: address → shipping → payment → complete, docs/API_FRONT.md §9).
import { fail, redirect, type ActionFailure, type RequestEvent } from '@sveltejs/kit';
import { CHECKOUT_STEPS, reachableStep, stepPosition, type CheckoutStep } from '#lib/order/checkout.js';
import type { Order } from '#lib/order/types.js';
import { currentCart } from './cart.js';
import { loginUrl } from './session.js';
import { ApiError } from './sylius.js';

type CheckoutEvent = Pick<RequestEvent, 'locals' | 'cookies' | 'url'>;

/**
 * Cart being checked out. Visitor → login; PRO → the cart page explains why ordering is closed;
 * empty cart → cart page.
 */
export async function checkoutCart(event: CheckoutEvent): Promise<Order> {
	const { viewer } = event.locals;
	if (!viewer.authenticated) redirect(303, loginUrl(event.url));
	if (!viewer.canOrder) redirect(303, '/panier');

	const cart = await currentCart(event);
	if (!cart || cart.items.length === 0) redirect(303, '/panier');
	return cart;
}

/** A step not reached yet (e.g. typed URL) sends back to the furthest step the cart may open. */
export function guardStep(cart: Order, step: CheckoutStep): void {
	const reachable = reachableStep(cart.checkoutState);
	if (stepPosition(step) > stepPosition(reachable)) {
		redirect(303, CHECKOUT_STEPS.find((s) => s.name === reachable)?.href ?? '/panier');
	}
}

/** Form-action answer for an API refusal (4xx: message shown in the form); 5xx are rethrown. */
export function apiFailure(error: unknown): ActionFailure<{ error: string }> {
	if (error instanceof ApiError && error.status < 500) return fail(error.status, { error: error.message });
	throw error;
}
