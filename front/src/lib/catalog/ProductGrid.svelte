<script lang="ts">
	import type { ProductSummary, Viewer } from '#lib/types.js';
	import ProductCard from './ProductCard.svelte';

	let { products, viewer }: { products: ProductSummary[]; viewer: Viewer } = $props();
</script>

<div class="products-grid">
	{#each products as product (product.code)}
		<div><ProductCard {product} {viewer} /></div>
	{/each}
</div>
