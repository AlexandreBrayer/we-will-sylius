const money = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' });

/** Sylius amount (cents) → "7 290,00 €". */
export function formatPrice(cents: number): string {
	return money.format(cents / 100);
}

const day = new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'Europe/Paris' });
const dayTime = new Intl.DateTimeFormat('fr-FR', { dateStyle: 'short', timeStyle: 'short', timeZone: 'Europe/Paris' });

/**
 * API date → Date. Sylius runs in UTC: "2026-11-06 10:02:31" (no offset) is UTC, ISO 8601 carries its offset,
 * a bare day ("2026-11-21") is read as noon UTC so it stays the same day in Paris. Same result on server and browser.
 */
function parseDate(value: string): Date {
	if (/^\d{4}-\d{2}-\d{2}$/.test(value)) return new Date(`${value}T12:00:00Z`);
	return new Date(value.includes('T') ? value : `${value.replace(' ', 'T')}Z`);
}

/** → "06/11/2026". */
export function formatDate(value: string): string {
	return day.format(parseDate(value));
}

/** → "06/11/2026 à 10:02". */
export function formatDateTime(value: string): string {
	return dayTime.format(parseDate(value)).replace(' ', ' à ');
}
