// Checkout steps: Adresse › Livraison › Paiement › Validation, then the confirmation page. The route of
// Validation stays /commande/recapitulatif (Sylius step `complete`).
import type { ProgressStep } from '#lib/layout/Progress.svelte';
import type { CheckoutState } from './types.js';

export type CheckoutStep = 'address' | 'select_shipping' | 'select_payment' | 'complete' | 'confirmation';

export const CHECKOUT_STEPS: (ProgressStep & { name: CheckoutStep; href: string | null })[] = [
	{ name: 'address', label: 'Adresse', href: '/commande/adresse' },
	{ name: 'select_shipping', label: 'Livraison', href: '/commande/livraison' },
	{ name: 'select_payment', label: 'Paiement', href: '/commande/paiement' },
	{ name: 'complete', label: 'Validation', href: '/commande/recapitulatif' },
	{ name: 'confirmation', label: 'Confirmation', href: null }
];

/**
 * Steps shown to the customer (stepper of /panier and of the tunnel): the cart, then the checkout up to
 * Validation. The confirmation is an end page, not a step: it stays in CHECKOUT_STEPS for the route guard.
 */
export const ORDER_STEPS: ProgressStep[] = [{ name: 'cart', label: 'Panier', href: '/panier' }, ...CHECKOUT_STEPS.filter((step) => step.name !== 'confirmation')];

/** 1-based position of a checkout step in ORDER_STEPS (the cart is 1). */
export const orderStepPosition = (step: CheckoutStep): number => stepPosition(step) + 1;

/** 1-based position of a step. */
export const stepPosition = (step: CheckoutStep): number => CHECKOUT_STEPS.findIndex((s) => s.name === step) + 1;

/** Furthest step the cart may open, from its Sylius checkout state. */
export function reachableStep(state: CheckoutState): CheckoutStep {
	switch (state) {
		case 'addressed':
			return 'select_shipping';
		case 'shipping_selected':
		case 'shipping_skipped':
			return 'select_payment';
		case 'payment_selected':
		case 'payment_skipped':
			return 'complete';
		case 'completed':
			return 'confirmation';
		default:
			return 'address';
	}
}

/** Step of a route id of the checkout (`/(shop)/commande/(tunnel)/livraison` → select_shipping). */
export function routeStep(routeId: string | null): CheckoutStep {
	if (routeId?.endsWith('/livraison')) return 'select_shipping';
	if (routeId?.endsWith('/paiement')) return 'select_payment';
	if (routeId?.endsWith('/recapitulatif')) return 'complete';
	if (routeId?.includes('/confirmation')) return 'confirmation';
	return 'address';
}

/**
 * Payment term shown next to a payment method: « 30 jours » for a bank transfer to pay within 30 days of the
 * order (`dueDays` of the API, read from config/packages/yack.yaml), unless the name already says it
 * (« Virement sous 15 jours »); null for a card.
 */
export function paymentTerm(method: { name: string; dueDays: number | null }): string | null {
	if (!method.dueDays || method.dueDays <= 0) return null;
	const term = `${method.dueDays} jours`;
	return method.name.includes(term) ? null : term;
}
