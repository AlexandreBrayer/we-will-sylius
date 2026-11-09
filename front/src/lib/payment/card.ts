// Card checks of the simulated hosted payment page (/paiement-securise/[session]): brand, Luhn,
// expiry, CVV. Pure module (`npm test`). Only the masked card (brand, last four digits, expiry)
// leaves the page server for Sylius; the number and CVV are never stored nor sent.

export type DetectedBrand = 'visa' | 'mastercard' | 'amex';

export const BRAND_LABELS: Record<DetectedBrand, string> = { visa: 'Visa', mastercard: 'Mastercard', amex: 'American Express' };

/** Test cards of the simulated provider: the outcome follows the last four digits (Sylius SimulatedScenario). */
export const TEST_CARDS: readonly { number: string; brand: DetectedBrand; outcome: string; threeDSecure: boolean }[] = [
	{ number: '4242 4242 4242 4242', brand: 'visa', outcome: 'Paiement accepté', threeDSecure: false },
	{ number: '5555 5555 5555 4444', brand: 'mastercard', outcome: 'Paiement accepté', threeDSecure: false },
	{ number: '4000 0000 0000 3220', brand: 'visa', outcome: 'Authentification 3-D Secure (code 123456), puis accepté', threeDSecure: true },
	{ number: '4000 0000 0000 9995', brand: 'visa', outcome: 'Refusé : fonds insuffisants', threeDSecure: false },
	{ number: '4000 0000 0000 0002', brand: 'visa', outcome: 'Refusé : carte refusée par la banque', threeDSecure: false }
];

export const CARD_MESSAGES = {
	number: 'Numéro de carte incomplet.',
	brand: 'Carte non acceptée : Visa, Mastercard ou American Express.',
	luhn: 'Numéro de carte invalide : vérifiez la saisie.',
	expiry: "Date d'expiration invalide (MM/AA).",
	expired: 'Cette carte est expirée.',
	cvv: 'Cryptogramme visuel invalide.'
} as const;

export function digitsOf(value: string): string {
	return value.replace(/\D/g, '');
}

/** Brand from the first digits (IIN ranges), null while unknown. */
export function detectBrand(digits: string): DetectedBrand | null {
	if (/^4/.test(digits)) return 'visa';
	if (/^3[47]/.test(digits)) return 'amex';
	if (/^5[1-5]/.test(digits)) return 'mastercard';
	const prefix = Number(digits.slice(0, 4));
	if (digits.length >= 4 && prefix >= 2221 && prefix <= 2720) return 'mastercard';
	return null;
}

/** Length of a full number of the brand. */
export function cardLength(brand: DetectedBrand | null): number {
	return brand === 'amex' ? 15 : 16;
}

export function cvvLength(brand: DetectedBrand | null): number {
	return brand === 'amex' ? 4 : 3;
}

/** Luhn checksum (mod 10). */
export function luhn(digits: string): boolean {
	if (!/^\d+$/.test(digits)) return false;
	let sum = 0;
	for (let i = 0; i < digits.length; i++) {
		let digit = Number(digits[digits.length - 1 - i]);
		if (i % 2 === 1) {
			digit *= 2;
			if (digit > 9) digit -= 9;
		}
		sum += digit;
	}
	return sum % 10 === 0;
}

/** Number grouped as printed on the card: 4-4-4-4, or 4-6-5 for American Express. */
export function formatCardNumber(value: string): string {
	const digits = digitsOf(value);
	const brand = detectBrand(digits);
	const limited = digits.slice(0, cardLength(brand));
	const groups = brand === 'amex' ? [4, 6, 5] : [4, 4, 4, 4];
	const parts: string[] = [];
	let at = 0;
	for (const size of groups) {
		if (at >= limited.length) break;
		parts.push(limited.slice(at, at + size));
		at += size;
	}
	return parts.join(' ');
}

/** "MMAA" typed → "MM/AA". */
export function formatExpiry(value: string): string {
	const digits = digitsOf(value).slice(0, 4);
	return digits.length > 2 ? `${digits.slice(0, 2)}/${digits.slice(2)}` : digits;
}

export interface MaskedCardInput {
	brand: DetectedBrand;
	last4: string;
	expiryMonth: number;
	expiryYear: number;
}

export type CardField = 'number' | 'expiry' | 'cvv';

export type CardCheck = { ok: true; card: MaskedCardInput } | { ok: false; errors: Partial<Record<CardField, string>> };

/** Typed card checked as the provider's page does; `now` decides whether the card is expired. */
export function checkCard(number: string, expiry: string, cvv: string, now: Date): CardCheck {
	const errors: Partial<Record<CardField, string>> = {};
	const digits = digitsOf(number);
	const brand = detectBrand(digits);
	if (!brand) errors.number = digits.length < 4 ? CARD_MESSAGES.number : CARD_MESSAGES.brand;
	else if (digits.length !== cardLength(brand)) errors.number = CARD_MESSAGES.number;
	else if (!luhn(digits)) errors.number = CARD_MESSAGES.luhn;

	const match = /^\s*(\d{2})\s*\/\s*(\d{2})\s*$/.exec(expiry);
	const month = match ? Number(match[1]) : 0;
	const year = match ? 2000 + Number(match[2]) : 0;
	if (!match || month < 1 || month > 12) errors.expiry = CARD_MESSAGES.expiry;
	else if (year < now.getFullYear() || (year === now.getFullYear() && month < now.getMonth() + 1)) errors.expiry = CARD_MESSAGES.expired;

	if (!new RegExp(`^\\d{${cvvLength(brand)}}$`).test(cvv.trim())) errors.cvv = CARD_MESSAGES.cvv;

	if (!brand || Object.keys(errors).length > 0) return { ok: false, errors };
	return { ok: true, card: { brand, last4: digits.slice(-4), expiryMonth: month, expiryYear: year } };
}

/** Amount in cents → "1 234,56 €" (currency of the session). */
export function formatAmount(cents: number, currencyCode: string): string {
	return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: currencyCode }).format(cents / 100);
}
