<script lang="ts">
	// Facets of a category listing (brand, range, power band, refrigerant), in the side column of
	// ProductListing. Each value is a link that sets or clears its URL parameter (?marque=, ?gamme=,
	// ?puissance=, ?fluide=; one value per facet, back to page 1): SSR, works without JavaScript.
	// Counts and greyed-out values only when the API counts match the listing (`counted`).
	import Icon from '#lib/layout/Icon.svelte';
	import { FILTER_PARAMS, type ListingFacets, type ListingFilters } from '#lib/types.js';

	let {
		facets,
		filters,
		link
	}: {
		facets: ListingFacets;
		filters: ListingFilters;
		/** URL of the listing with some parameters changed ('' clears one). */
		link: (changes: Record<string, string>) => string;
	} = $props();

	const shown = $derived(facets.facets.filter((facet) => facet.values.length > 0));
	const active = $derived(Object.values(filters).some((value) => value !== undefined));
	const clearAll = $derived(link(Object.fromEntries(Object.values(FILTER_PARAMS).map((param) => [param, '']))));
</script>

{#if shown.length > 0}
	<section class="yack-listing__panel yack-facets" aria-labelledby="yack-facets-title" data-test-facets>
		<div class="yack-facets__head">
			<h2 class="yack-listing__panel-title mb-0" id="yack-facets-title">Filtres</h2>
			{#if active}<a class="yack-facets__clear" href={clearAll} data-test-facets-clear>Tout effacer</a>{/if}
		</div>
		{#each shown as facet (facet.code)}
			<details class="yack-facet" open>
				<summary>{facet.label}<Icon name="chevron" /></summary>
				<ul>
					{#each facet.values as value (value.value)}
						{@const selected = filters[facet.code] === value.value}
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
									href={link({ [FILTER_PARAMS[facet.code]]: selected ? '' : value.value })}
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
