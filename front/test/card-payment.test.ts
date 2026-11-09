// Tests du module pur de la page de paiement simulée (src/lib/payment/card.ts) : `npm test`.
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
	CARD_MESSAGES,
	TEST_CARDS,
	checkCard,
	detectBrand,
	digitsOf,
	formatAmount,
	formatCardNumber,
	formatExpiry,
	luhn
} from '../src/lib/payment/card.ts';

const NOW = new Date(2026, 10, 9);

describe('detectBrand', () => {
	it('reads the brand from the first digits', () => {
		assert.equal(detectBrand('4242'), 'visa');
		assert.equal(detectBrand('5555'), 'mastercard');
		assert.equal(detectBrand('2221000000000009'), 'mastercard');
		assert.equal(detectBrand('3782'), 'amex');
		assert.equal(detectBrand('6011'), null);
		assert.equal(detectBrand(''), null);
	});
});

describe('luhn', () => {
	it('accepts every test card and rejects a typo', () => {
		for (const card of TEST_CARDS) assert.equal(luhn(digitsOf(card.number)), true, card.number);
		assert.equal(luhn('4242424242424241'), false);
		assert.equal(luhn('abc'), false);
	});
});

describe('formatting', () => {
	it('groups the number as printed and the expiry as MM/AA', () => {
		assert.equal(formatCardNumber('4242424242424242999'), '4242 4242 4242 4242');
		assert.equal(formatCardNumber('378282246310005'), '3782 822463 10005');
		assert.equal(formatExpiry('1228'), '12/28');
		assert.equal(formatExpiry('1'), '1');
		assert.equal(formatAmount(19910, 'EUR'), '199,10\u00a0€');
	});
});

describe('checkCard', () => {
	it('keeps only the masked card of a valid card', () => {
		assert.deepEqual(checkCard('4242 4242 4242 4242', '12/28', '123', NOW), {
			ok: true,
			card: { brand: 'visa', last4: '4242', expiryMonth: 12, expiryYear: 2028 }
		});
		assert.deepEqual(checkCard('3782 822463 10005', '11/26', '1234', NOW), {
			ok: true,
			card: { brand: 'amex', last4: '0005', expiryMonth: 11, expiryYear: 2026 }
		});
	});

	it('explains every wrong field', () => {
		assert.deepEqual(checkCard('4242 4242 4242 4241', '13/28', '12', NOW), {
			ok: false,
			errors: { number: CARD_MESSAGES.luhn, expiry: CARD_MESSAGES.expiry, cvv: CARD_MESSAGES.cvv }
		});
		assert.deepEqual(checkCard('6011 1111 1111 1117', '10/26', '123', NOW), { ok: false, errors: { number: CARD_MESSAGES.brand, expiry: CARD_MESSAGES.expired } });
		assert.deepEqual(checkCard('4242 4242', '12/28', '123', NOW), { ok: false, errors: { number: CARD_MESSAGES.number } });
		assert.deepEqual(checkCard('3782 822463 10005', '12/28', '123', NOW), { ok: false, errors: { cvv: CARD_MESSAGES.cvv } });
	});
});
