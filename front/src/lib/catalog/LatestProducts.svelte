<script lang="ts">
	import type { ProductSummary, Viewer } from '#lib/types.js';
	import ProductGrid from './ProductGrid.svelte';

	let { products, viewer }: { products: ProductSummary[]; viewer: Viewer } = $props();
</script>

<!-- Sylius "Derniers produits" section (homepage and product page). -->
{#if products.length > 0}
	<div class="container mb-6" role="region" aria-labelledby="latest-products-heading">
		<h2 class="mb-5" id="latest-products-heading">Derniers produits</h2>
		<div data-test-latest-products>
			<ProductGrid {products} {viewer} />
		</div>
	</div>
{/if}
