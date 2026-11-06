const money = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' });

/** Sylius amount (cents) → "7 290,00 €". */
export function formatPrice(cents: number): string {
	return money.format(cents / 100);
}
