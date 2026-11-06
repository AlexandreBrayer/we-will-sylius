<script lang="ts">
	// Sylius "Derniers produits" section (homepage and product page): secondary content, it does
	// not hold a client-side navigation (skeleton meanwhile); fully rendered by SSR.
	import Deferred from '#lib/layout/Deferred.svelte';
	import { getLatestProducts } from '#lib/remote/catalog.remote.js';
	import { getViewer } from '#lib/remote/session.remote.js';
	import ProductGrid from './ProductGrid.svelte';
	import ProductGridSkeleton from './ProductGridSkeleton.svelte';
</script>

<Deferred>
	{@const products = await getLatestProducts()}
	{#if products.length > 0}
		<div class="container mb-6" role="region" aria-labelledby="latest-products-heading">
			<h2 class="mb-5" id="latest-products-heading">Derniers produits</h2>
			<div data-test-latest-products>
				<ProductGrid {products} viewer={await getViewer()} />
			</div>
		</div>
	{/if}

	{#snippet pending()}
		<div class="container mb-6">
			<h2 class="mb-5">Derniers produits</h2>
			<ProductGridSkeleton />
		</div>
	{/snippet}
</Deferred>
