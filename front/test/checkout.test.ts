// Tests of the checkout steps (src/lib/order/checkout.ts): stepper of the cart and of the tunnel, route
// guard steps. `npm test`.
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { CHECKOUT_STEPS, ORDER_STEPS, orderStepPosition, reachableStep, routeStep, stepPosition } from '../src/lib/order/checkout.ts';

describe('ORDER_STEPS', () => {
	it('shows five steps, from the cart to Validation, without the confirmation page', () => {
		assert.deepEqual(
			ORDER_STEPS.map((step) => step.label),
			['Panier', 'Adresse', 'Livraison', 'Paiement', 'Validation']
		);
		assert.equal(ORDER_STEPS.some((step) => step.name === 'confirmation'), false);
	});

	it('keeps the route of Validation and its Sylius step', () => {
		assert.deepEqual(ORDER_STEPS.at(-1), { name: 'complete', label: 'Validation', href: '/commande/recapitulatif' });
		assert.equal(routeStep('/(shop)/commande/(tunnel)/recapitulatif'), 'complete');
	});

	it('places each tunnel step after the cart', () => {
		assert.equal(orderStepPosition('address'), 2);
		assert.equal(orderStepPosition('complete'), ORDER_STEPS.length);
	});
});

describe('CHECKOUT_STEPS', () => {
	it('keeps the confirmation for the route guard', () => {
		assert.equal(CHECKOUT_STEPS.at(-1)?.name, 'confirmation');
		assert.equal(reachableStep('completed'), 'confirmation');
		assert.ok(stepPosition('confirmation') > stepPosition('complete'));
		assert.equal(routeStep('/(shop)/commande/confirmation/[token]'), 'confirmation');
	});
});
