<script lang="ts">
	// Facets of a category listing (brand, range, power band, refrigerant, price range), in the side
	// column of ProductListing. Each value is a checkbox-like link toggling it in its repeated URL
	// parameter (?marque=MHI&marque=RHOSS, also ?gamme=, ?puissance=, ?fluide=): the other values stay,
	// any value of a facet matches, all facets together; back to page 1. The price range (PriceFilter,
	// ?prix_min=&prix_max=) comes last, for the viewers who see prices only (`facets.price` not null).
	// The active values are recalled as removable chips under the title. SSR, works without JavaScript.
	// Counts and greyed-out values only when the API counts match the listing (`counted`); a selected
	// value is never greyed out.
	import Icon from '#lib/layout/Icon.svelte';
	import { FILTER_PARAMS, PRICE_PARAMS, type FacetCode, type ListingFacets, type ListingFilters, type PriceRange } from '#lib/types.js';
	import { isSelected, toggleValue, type UrlChange } from './listingUrl.js';
	import PriceFilter from './PriceFilter.svelte';
	import { priceLabel } from './priceRange.js';

	let {
		facets,
		filters,
		price,
		link
	}: {
		facets: ListingFacets;
		filters: ListingFilters;
		/** Price range of the URL (taken into account only when `facets.price` is set). */
		price: PriceRange;
		/** URL of the listing with some parameters changed ('' or [] clears one). */
		link: (changes: Record<string, UrlChange>) => string;
	} = $props();

	const NO_PRICE = { [PRICE_PARAMS.min]: '', [PRICE_PARAMS.max]: '' };

	const shown = $derived(facets.facets.filter((facet) => facet.values.length > 0));
	const clearAll = $derived(link({ ...Object.fromEntries(Object.values(FILTER_PARAMS).map((param) => [param, []])), ...NO_PRICE }));
	// Active values, in the order of the facets: label of the facet value (power band "6 à 9 kW").
	const chips = $derived(
		(Object.keys(FILTER_PARAMS) as FacetCode[]).flatMap((code) =>
			(filters[code] ?? []).map((value) => ({
				code,
				value,
				label: facets.facets.find((facet) => facet.code === code)?.values.find((v) => isSelected([v.value], value))?.label ?? value
			}))
		)
	);
	// Active price range ("500 € à 2 000 € HT"), for the viewers who see prices only.
	const priceChip = $derived(facets.price ? priceLabel(price) : null);
	const active = $derived(chips.length > 0 || priceChip !== null);

	/** Link toggling one value of a facet, the other values kept. */
	function toggle(code: FacetCode, value: string): string {
		return link({ [FILTER_PARAMS[code]]: toggleValue(filters[code], value) });
	}
</script>

{#if shown.length > 0 || facets.price}
	<section class="yack-listing__panel yack-facets" aria-labelledby="yack-facets-title" data-test-facets>
		<div class="yack-facets__head">
			<h2 class="yack-listing__panel-title mb-0" id="yack-facets-title">Filtres</h2>
			{#if active}<a class="yack-facets__clear" href={clearAll} data-test-facets-clear>Tout effacer</a>{/if}
		</div>
		{#if active}
			<ul class="yack-facets__chips" aria-label="Filtres actifs">
				{#each chips as chip (`${chip.code}:${chip.value}`)}
					<li>
						<a class="yack-facets__chip" href={toggle(chip.code, chip.value)} data-test-facet-chip="{chip.code}:{chip.value}">
							{chip.label}<Icon name="close" /><span class="visually-hidden"> (retirer ce filtre)</span>
						</a>
					</li>
				{/each}
				{#if priceChip}
					<li>
						<a class="yack-facets__chip" href={link(NO_PRICE)} data-test-facet-chip="price">
							{priceChip}<Icon name="close" /><span class="visually-hidden"> (retirer ce filtre)</span>
						</a>
					</li>
				{/if}
			</ul>
		{/if}
		{#each shown as facet (facet.code)}
			<details class="yack-facet" open>
				<summary>{facet.label}<Icon name="chevron" /></summary>
				<ul>
					{#each facet.values as value (value.value)}
						{@const selected = isSelected(filters[facet.code], value.value)}
						<li>
							{#if facets.counted && value.count === 0 && !selected}
								<span class="yack-facet__value" aria-disabled="true" data-test-facet-value="{facet.code}:{value.value}">
									<span class="yack-facet__box"></span>
									<span class="yack-facet__label">{value.label}</span>
									<small>0</small>
								</span>
							{:else}
								<a
									class="yack-facet__value"
									href={toggle(facet.code, value.value)}
									aria-current={selected ? 'true' : undefined}
									data-test-facet-value="{facet.code}:{value.value}"
								>
									<span class="yack-facet__box">{#if selected}<Icon name="check" />{/if}</span>
									<span class="yack-facet__label">{value.label}{#if selected}<span class="visually-hidden"> (filtre actif, retirer)</span>{/if}</span>
									{#if facets.counted}<small>{value.count}</small>{/if}
								</a>
							{/if}
						</li>
					{/each}
				</ul>
			</details>
		{/each}
		{#if facets.price}
			<PriceFilter bounds={facets.price} range={price} {link} />
		{/if}
	</section>
{/if}
