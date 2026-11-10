<script lang="ts">
	import ProductCard from '#lib/catalog/ProductCard.svelte';
	import { getYackContext } from './context.js';
	import { useCatalog } from './catalog.svelte.js';
	import { textEditor } from './editable.js';
	import { parseCodes } from './parse.js';
	import { titleTag } from './style.js';
	import { blockStyle } from './styleContext.js';

	let { title = '', subtitle = '', productCodes = '' } = $props();
	const ctx = getYackContext();
	const edit = textEditor();
	// Tag of the main title (style `balise`, h2 by default) and its look: styleContext.ts, style.ts titleTag().
	const style = blockStyle();
	const heading = $derived(titleTag(style()));
	const codes = $derived(parseCodes(productCodes));
	const catalog = useCatalog(ctx, () => ({ products: codes }));
</script>

<section class="yack-cms-section container" data-yack-block="produits">
	{#if title}<svelte:element this={heading.tag} class={['yack-section-title', heading.look]} {@attach edit('title')}>{title}</svelte:element>{/if}
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
