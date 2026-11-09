// Tests of the checkout steps (src/lib/order/checkout.ts): stepper of the cart and of the tunnel, route
// guard steps, payment term of a method. `npm test`.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import { CHECKOUT_STEPS, ORDER_STEPS, orderStepPosition, paymentTerm, reachableStep, routeStep, stepPosition } from '../src/lib/order/checkout.ts';

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

describe('paymentTerm', () => {
	it('dit le délai du virement à échéance, lu dans la configuration', () => {
		// config/packages/yack.yaml : app.yack.payment_terms.methods.virement_echeance.due_days, renvoyé par l'API en dueDays.
		const days = readFileSync(new URL('../../config/packages/yack.yaml', import.meta.url), 'utf8').match(/\n {8}virement_echeance: \{ kind: 'virement', due_days: (\d+) \}/)?.[1];
		assert.equal(days, '30');
		assert.equal(paymentTerm({ name: 'Virement à échéance', dueDays: Number(days) }), '30 jours');
	});

	it("ne répète pas un délai que le nom dit déjà, et n'en donne aucun à la carte", () => {
		assert.equal(paymentTerm({ name: 'Virement sous 15 jours', dueDays: 15 }), null);
		assert.equal(paymentTerm({ name: 'Virement sous 15 jours', dueDays: 45 }), '45 jours');
		assert.equal(paymentTerm({ name: 'Carte bancaire', dueDays: null }), null);
		assert.equal(paymentTerm({ name: 'Virement', dueDays: 0 }), null);
	});
});
