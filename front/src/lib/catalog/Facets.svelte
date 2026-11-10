<script lang="ts">
	// Facets of a category listing (brand, range, power band, refrigerant), in the side column of
	// ProductListing. Each value is a checkbox-like link toggling it in its repeated URL parameter
	// (?marque=MHI&marque=RHOSS, also ?gamme=, ?puissance=, ?fluide=): the other values stay, any
	// value of a facet matches, all facets together; back to page 1. The active values are recalled
	// as removable chips under the title. SSR, works without JavaScript. Counts and greyed-out values
	// only when the API counts match the listing (`counted`); a selected value is never greyed out.
	import Icon from '#lib/layout/Icon.svelte';
	import { FILTER_PARAMS, type FacetCode, type ListingFacets, type ListingFilters } from '#lib/types.js';
	import { isSelected, toggleValue, type UrlChange } from './listingUrl.js';

	let {
		facets,
		filters,
		link
	}: {
		facets: ListingFacets;
		filters: ListingFilters;
		/** URL of the listing with some parameters changed ('' or [] clears one). */
		link: (changes: Record<string, UrlChange>) => string;
	} = $props();

	const shown = $derived(facets.facets.filter((facet) => facet.values.length > 0));
	const clearAll = $derived(link(Object.fromEntries(Object.values(FILTER_PARAMS).map((param) => [param, []]))));
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

	/** Link toggling one value of a facet, the other values kept. */
	function toggle(code: FacetCode, value: string): string {
		return link({ [FILTER_PARAMS[code]]: toggleValue(filters[code], value) });
	}
</script>

{#if shown.length > 0}
	<section class="yack-listing__panel yack-facets" aria-labelledby="yack-facets-title" data-test-facets>
		<div class="yack-facets__head">
			<h2 class="yack-listing__panel-title mb-0" id="yack-facets-title">Filtres</h2>
			{#if chips.length > 0}<a class="yack-facets__clear" href={clearAll} data-test-facets-clear>Tout effacer</a>{/if}
		</div>
		{#if chips.length > 0}
			<ul class="yack-facets__chips" aria-label="Filtres actifs">
				{#each chips as chip (`${chip.code}:${chip.value}`)}
					<li>
						<a class="yack-facets__chip" href={toggle(chip.code, chip.value)} data-test-facet-chip="{chip.code}:{chip.value}">
							{chip.label}<Icon name="close" /><span class="visually-hidden"> (retirer ce filtre)</span>
						</a>
					</li>
				{/each}
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
	</section>
{/if}
