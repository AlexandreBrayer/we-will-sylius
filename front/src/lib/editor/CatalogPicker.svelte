<script lang="ts">
	// Builder search field: products (name / code, admin API) or catalog categories (name / path /
	// code); picking a result calls `onpick`. Template sample and assignments.
	import { searchProducts, searchTaxons } from '#lib/remote/builder.remote.js';

	type Item = { code: string; name: string; path?: string };

	let {
		kind,
		label,
		placeholder,
		exclude = [],
		onpick
	}: {
		kind: 'product' | 'taxon';
		label: string;
		placeholder: string;
		/** Codes not offered (already assigned / current sample). */
		exclude?: string[];
		onpick: (item: Item) => void;
	} = $props();

	let q = $state('');
	let results = $state<Item[]>([]);
	let loading = $state(false);
	const id = $props.id();

	// Debounced search (products: 2 characters minimum, as the admin API search).
	$effect(() => {
		const term = q.trim();
		if (term.length < (kind === 'product' ? 2 : 1)) {
			results = [];
			return;
		}
		let cancelled = false;
		const timer = setTimeout(async () => {
			loading = true;
			try {
				const found: Item[] = kind === 'product' ? await searchProducts(term) : await searchTaxons(term);
				if (!cancelled) results = found;
			} finally {
				if (!cancelled) loading = false;
			}
		}, 250);
		return () => {
			cancelled = true;
			clearTimeout(timer);
		};
	});

	const offered = $derived(results.filter((item) => !exclude.includes(item.code)));

	function pick(item: Item) {
		onpick(item);
		q = '';
		results = [];
	}
</script>

<div class="picker">
	<label class="yb-field" for="{id}-q">{label}</label>
	<input id="{id}-q" type="search" bind:value={q} {placeholder} autocomplete="off" data-test-picker={kind} />
	{#if q.trim() && (offered.length > 0 || !loading)}
		<ul role="listbox" aria-label={label}>
			{#each offered as item (item.code)}
				<li>
					<button type="button" onclick={() => pick(item)} data-test-pick={item.code}>
						<span>{item.path ?? item.name}</span>
						<code>{item.code}</code>
					</button>
				</li>
			{:else}
				<li class="empty">{kind === 'product' && q.trim().length < 2 ? '2 caractères minimum' : 'Aucun résultat'}</li>
			{/each}
		</ul>
	{/if}
</div>

<style>
	.picker {
		position: relative;
		margin-bottom: 0.9rem;
	}
	.picker .yb-field {
		margin-bottom: 0.3rem;
	}
	input {
		width: 100%;
		box-sizing: border-box;
		padding: 0.45rem 0.55rem;
		font: inherit;
		font-size: 0.85rem;
		border: 1px solid var(--yack-border);
		border-radius: var(--yack-radius-pill);
	}
	ul {
		position: absolute;
		z-index: 10;
		left: 0;
		right: 0;
		max-height: 260px;
		overflow-y: auto;
		margin: 0.2rem 0 0;
		padding: 0.25rem;
		list-style: none;
		background: var(--yack-white);
		border: 1px solid var(--yack-border);
		border-radius: var(--yack-radius-card);
		box-shadow: 0 6px 18px var(--yack-shadow-soft);
	}
	button {
		display: flex;
		flex-direction: column;
		align-items: flex-start;
		width: 100%;
		padding: 0.4rem 0.5rem;
		border: 0;
		background: none;
		font: inherit;
		font-size: 0.82rem;
		text-align: left;
		cursor: pointer;
		border-radius: var(--yack-radius-xs);
	}
	button:hover {
		background: var(--yack-magenta-10);
	}
	code {
		font-size: 0.7rem;
		color: var(--yack-gray);
	}
	.empty {
		padding: 0.4rem 0.5rem;
		font-size: 0.8rem;
		color: var(--yack-gray);
	}
</style>
