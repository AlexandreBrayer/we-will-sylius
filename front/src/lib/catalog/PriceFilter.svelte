<script lang="ts">
	// Price range filter of a category listing (in Facets), shown only to the viewers who see prices:
	// the API sends the bounds to them alone, and ignores ?prix_min=&prix_max= for the others. Euros HT,
	// bounds included. Without JavaScript: GET form on the current URL, "Min" / "Max" fields and
	// "Appliquer", the other parameters kept as hidden fields. With JavaScript: a two-thumb slider (two
	// native range inputs on top of each other, each labelled and keyboard usable) fills the fields and
	// loads the listing once released (debounced); the form loads it the same way. Back to page 1, the
	// other filters kept; a bound at the end of the scale is left out of the URL.
	import { goto } from '$app/navigation';
	import { page } from '$app/state';
	import { onMount } from 'svelte';
	import Icon from '#lib/layout/Icon.svelte';
	import { PRICE_PARAMS, type PriceBounds, type PriceRange } from '#lib/types.js';
	import type { UrlChange } from './listingUrl.js';
	import { formatEuros, priceChanges, priceScale } from './priceRange.js';

	let {
		bounds,
		range,
		link
	}: {
		/** Lowest and highest prices of the listing, the price range aside. */
		bounds: PriceBounds;
		/** Price range of the URL. */
		range: PriceRange;
		/** URL of the listing with some parameters changed ('' clears one). */
		link: (changes: Record<string, UrlChange>) => string;
	} = $props();

	/** Pause after the last move of a thumb before loading the listing. */
	const SETTLE_MS = 400;
	/** Parameters the form does not carry over: the price range itself, the page (back to 1), ?par= of an old link. */
	const DROPPED = new Set<string>([PRICE_PARAMS.min, PRICE_PARAMS.max, 'page', 'par']);

	const scale = $derived(priceScale(bounds));
	// Fields: the range of the URL, then what is typed or slid (empty: no bound).
	let min: number | null | undefined = $derived(range.min);
	let max: number | null | undefined = $derived(range.max);
	const low = $derived(clamp(min ?? scale.min));
	const high = $derived(clamp(max ?? scale.max));
	const kept = $derived([...page.url.searchParams].filter(([name]) => !DROPPED.has(name)));

	let hydrated = $state(false);
	let timer: ReturnType<typeof setTimeout> | undefined;
	onMount(() => {
		hydrated = true;
		return () => clearTimeout(timer);
	});

	function clamp(value: number): number {
		return Math.min(Math.max(value, scale.min), scale.max);
	}

	/** Position of a price on the slider track, in percent (filled part between the thumbs). */
	function percent(value: number): number {
		return ((value - scale.min) / (scale.max - scale.min)) * 100;
	}

	/** Thumb moved: its field follows, without crossing the other thumb; a thumb at the end empties its field. */
	function slide(side: 'min' | 'max', input: HTMLInputElement): void {
		const value = side === 'min' ? Math.min(Number(input.value), high) : Math.max(Number(input.value), low);
		input.value = String(value);
		if (side === 'min') min = value <= scale.min ? null : value;
		else max = value >= scale.max ? null : value;
	}

	/** Loads the listing with the range of the fields (nothing when unchanged). */
	function load(): void {
		clearTimeout(timer);
		const target = link(priceChanges(min ?? null, max ?? null, scale));
		if (target !== `${page.url.pathname}${page.url.search}`) void goto(target, { reset: false });
	}

	/** Thumb released (pointer up, or a key press): the listing loads once the moves settle. */
	function settle(): void {
		clearTimeout(timer);
		timer = setTimeout(load, SETTLE_MS);
	}

	function submit(event: SubmitEvent): void {
		event.preventDefault();
		load();
	}
</script>

<details class="yack-facet yack-price" open data-test-price-filter>
	<summary>Prix (€ HT)<Icon name="chevron" /></summary>
	<form class="yack-price__form" method="get" action={page.url.pathname} onsubmit={submit}>
		{#each kept as [name, value], i (i)}
			<input type="hidden" {name} {value} />
		{/each}
		{#if hydrated}
			<div class="yack-price__slider" style:--yack-price-low="{percent(low)}%" style:--yack-price-high="{percent(high)}%">
				<!-- The min thumb goes over the max one in the upper half: two thumbs at the top stay movable. -->
				<input
					class:yack-price__top={percent(low) > 50}
					type="range"
					min={scale.min}
					max={scale.max}
					step={scale.step}
					value={low}
					aria-label="Prix minimum (€ HT)"
					aria-valuetext={formatEuros(low)}
					oninput={(event) => slide('min', event.currentTarget)}
					onchange={settle}
					data-test-price-thumb="min"
				/>
				<input
					type="range"
					min={scale.min}
					max={scale.max}
					step={scale.step}
					value={high}
					aria-label="Prix maximum (€ HT)"
					aria-valuetext={formatEuros(high)}
					oninput={(event) => slide('max', event.currentTarget)}
					onchange={settle}
					data-test-price-thumb="max"
				/>
			</div>
		{/if}
		<div class="yack-price__fields">
			<label class="yack-price__field">
				<span>Min</span>
				<input
					type="number"
					name={PRICE_PARAMS.min}
					min="0"
					step="any"
					inputmode="decimal"
					placeholder={String(scale.min)}
					bind:value={min}
					data-test-price-input="min"
				/>
				<span aria-hidden="true">€</span>
			</label>
			<label class="yack-price__field">
				<span>Max</span>
				<input
					type="number"
					name={PRICE_PARAMS.max}
					min="0"
					step="any"
					inputmode="decimal"
					placeholder={String(scale.max)}
					bind:value={max}
					data-test-price-input="max"
				/>
				<span aria-hidden="true">€</span>
			</label>
		</div>
		<button class="btn btn-outline-primary btn-sm yack-price__apply" type="submit" data-test-price-apply>Appliquer</button>
	</form>
</details>
