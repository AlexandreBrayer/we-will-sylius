<script lang="ts">
	import ProductCard from '#lib/catalog/ProductCard.svelte';
	import { getYackContext } from './context.js';
	import { useCatalog } from './catalog.svelte.js';
	import { parseCodes } from './parse.js';

	let { title = '', subtitle = '', productCodes = '' } = $props();
	const ctx = getYackContext();
	const codes = $derived(parseCodes(productCodes));
	const catalog = useCatalog(ctx, () => ({ products: codes }));
</script>

<section class="yack-cms-section container" data-yack-block="produits">
	{#if title}<h2 class="mb-2">{title}</h2>{/if}
	{#if subtitle}<p class="text-body-secondary mb-5">{subtitle}</p>{/if}
	<div class="products-grid">
		{#each codes as code (code)}
			{@const product = catalog.current?.products[code]}
			{#if product}
				<ProductCard {product} viewer={ctx.viewer} />
			{:else if ctx.editor && catalog.current}
				<div class="small text-danger border rounded p-3">Produit « {code} » introuvable ou inactif</div>
			{/if}
		{/each}
	</div>
</section>
