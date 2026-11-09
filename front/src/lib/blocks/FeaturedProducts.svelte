<script lang="ts">
	import ProductCard from '#lib/catalog/ProductCard.svelte';
	import { getYackContext } from './context.js';
	import { useCatalog } from './catalog.svelte.js';
	import { textEditor } from './editable.js';
	import { parseCodes } from './parse.js';

	let { title = '', subtitle = '', productCodes = '' } = $props();
	const ctx = getYackContext();
	const edit = textEditor();
	const codes = $derived(parseCodes(productCodes));
	const catalog = useCatalog(ctx, () => ({ products: codes }));
</script>

<section class="yack-cms-section container" data-yack-block="produits">
	{#if title}<h2 class="yack-section-title" {@attach edit('title')}>{title}</h2>{/if}
	{#if subtitle}<p class="text-body-secondary mb-5" {@attach edit('subtitle')}>{subtitle}</p>{/if}
	<ul class="yack-product-grid yack-product-grid--4">
		{#each codes as code (code)}
			{@const product = catalog.current?.products[code]}
			{#if product}
				<li><ProductCard {product} viewer={ctx.viewer} /></li>
			{:else if ctx.editor && catalog.current}
				<li class="small text-danger border rounded p-3">Produit « {code} » introuvable ou inactif</li>
			{/if}
		{/each}
	</ul>
</section>
