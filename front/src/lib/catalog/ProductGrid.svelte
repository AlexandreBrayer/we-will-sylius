<script lang="ts">
	import type { ProductSummary, Viewer } from '#lib/types.js';
	import ProductCard from './ProductCard.svelte';

	let { products, viewer }: { products: ProductSummary[]; viewer: Viewer } = $props();
</script>

<ul class="yack-product-grid">
	{#each products as product (product.code)}
		<li><ProductCard {product} {viewer} /></li>
	{/each}
</ul>
