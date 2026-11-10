// Links of a product listing (ProductListing, Facets): the current URL with some parameters changed,
// back to page 1 unless set; a facet value toggles in or out of its repeated parameter
// (?marque=MHI&marque=RHOSS) and keeps the other values. `npm test` (test/listing.test.ts).

/** New value of a parameter: '' clears it, a list replaces all its values ([] clears it). */
export type UrlChange = string | number | readonly string[];

/**
 * `url` with `changes` applied; a value equal to its `initial` one is left out (?tri= of the initial
 * sort, ?page=1). ?par= of an old link is dropped: the page size is not a URL parameter.
 */
export function listingUrl(url: { pathname: string; search: string }, changes: Record<string, UrlChange>, initial: Record<string, string> = {}): string {
	const query = new URLSearchParams(url.search);
	query.delete('page');
	query.delete('par');
	for (const [name, value] of Object.entries(changes)) {
		query.delete(name);
		for (const item of typeof value === 'object' ? value : [String(value)]) {
			if (item !== '' && item !== initial[name]) query.append(name, item);
		}
	}
	const search = query.toString();
	return search ? `${url.pathname}?${search}` : url.pathname;
}

/** Whether `value` is among the selected values of a facet (case aside, as the API filters). */
export function isSelected(values: readonly string[] | undefined, value: string): boolean {
	return (values ?? []).some((selected) => selected.toLowerCase() === value.toLowerCase());
}

/** Selected values of a facet once `value` is toggled: removed when selected (case aside), added otherwise. */
export function toggleValue(values: readonly string[] | undefined, value: string): string[] {
	const current = values ?? [];
	return isSelected(current, value) ? current.filter((selected) => selected.toLowerCase() !== value.toLowerCase()) : [...current, value];
}
