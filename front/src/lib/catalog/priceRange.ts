// Price range filter of a listing (PriceFilter, Facets): scale of the slider, URL changes of a range
// and label of the active range. Euros HT, bounds included. `npm test` (test/listing.test.ts).
import { PRICE_PARAMS, type PriceBounds, type PriceRange } from '../types.ts';

/** Slider scale: whole steps around the prices of the listing. */
export interface PriceScale {
	min: number;
	max: number;
	step: number;
}

/** Scale of the slider: bounds of the listing widened to whole steps (10 € from a 1 000 € span, 1 € below). */
export function priceScale(bounds: PriceBounds): PriceScale {
	const step = bounds.max - bounds.min >= 1000 ? 10 : 1;
	const min = Math.floor(bounds.min / step) * step;
	return { min, max: Math.max(Math.ceil(bounds.max / step) * step, min + step), step };
}

/**
 * URL changes of a price range: a bound left empty, or reaching the end of the scale (every product
 * of the listing on that side), is dropped; the other filters are kept by the caller (listingUrl).
 */
export function priceChanges(min: number | null, max: number | null, scale: PriceScale): Record<string, string> {
	return {
		[PRICE_PARAMS.min]: min === null || min <= scale.min ? '' : String(min),
		[PRICE_PARAMS.max]: max === null || max >= scale.max ? '' : String(max)
	};
}

const euros = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', minimumFractionDigits: 0, maximumFractionDigits: 2 });

/** Amount in euros → "2 000 €", "1 499,9 €" (no decimals when whole). */
export function formatEuros(amount: number): string {
	return euros.format(amount);
}

/** Label of an active price range ("500 € à 2 000 € HT", "Dès 500 € HT", "Jusqu'à 2 000 € HT"); null without bound. */
export function priceLabel(range: PriceRange): string | null {
	if (range.min !== null && range.max !== null) return `${formatEuros(range.min)} à ${formatEuros(range.max)} HT`;
	if (range.min !== null) return `Dès ${formatEuros(range.min)} HT`;
	if (range.max !== null) return `Jusqu'à ${formatEuros(range.max)} HT`;
	return null;
}
