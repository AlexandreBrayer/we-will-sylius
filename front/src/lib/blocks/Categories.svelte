<script lang="ts">
	import { getYackContext } from './context.js';
	import { useCatalog } from './catalog.svelte.js';
	import { parseCodes } from './parse.js';

	let { title = '', taxonCodes = '' } = $props();
	const ctx = getYackContext();
	const codes = $derived(parseCodes(taxonCodes));
	const catalog = useCatalog(ctx, () => ({ taxons: codes }));
</script>

<section class="yack-cms-section container" data-yack-block="categories">
	{#if title}<h2 class="mb-5">{title}</h2>{/if}
	<div class="yack-cms-categories">
		{#each codes as code (code)}
			{@const taxon = catalog.current?.taxons[code]}
			{#if taxon}
				<a class="yack-cms-category" href={taxon.url}>
					<span class="yack-cms-category__kicker">Gamme</span>
					<span class="yack-cms-category__name">{taxon.name}</span>
					<span class="yack-cms-category__more">Voir la gamme →</span>
				</a>
			{:else if ctx.editor && catalog.current}
				<div class="small text-danger border rounded p-3">Catégorie « {code} » introuvable</div>
			{/if}
		{/each}
	</div>
</section>
