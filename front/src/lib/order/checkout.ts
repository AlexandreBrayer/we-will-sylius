// Checkout steps: Adresse › Livraison › Paiement › Récapitulatif › Confirmation
// (port of templates/shop/yack/checkout/steps.html.twig).
import type { ProgressStep } from '#lib/layout/Progress.svelte';
import type { CheckoutState } from './types.js';

export type CheckoutStep = 'address' | 'select_shipping' | 'select_payment' | 'complete' | 'confirmation';

export const CHECKOUT_STEPS: (ProgressStep & { name: CheckoutStep; href: string | null })[] = [
	{ name: 'address', label: 'Adresse', href: '/commande/adresse' },
	{ name: 'select_shipping', label: 'Livraison', href: '/commande/livraison' },
	{ name: 'select_payment', label: 'Paiement', href: '/commande/paiement' },
	{ name: 'complete', label: 'Récapitulatif', href: '/commande/recapitulatif' },
	{ name: 'confirmation', label: 'Confirmation', href: null }
];

/** Steps shown to the customer: the cart, then the checkout (stepper of /panier, the tunnel and the confirmation). */
export const ORDER_STEPS: ProgressStep[] = [{ name: 'cart', label: 'Panier', href: '/panier' }, ...CHECKOUT_STEPS];

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
