<script lang="ts">
	// Placeholder of ProductGrid while its products load (client-side navigation, see #lib/layout/Deferred.svelte).
	let { count = 4 }: { count?: number } = $props();
</script>

<div class="products-grid placeholder-glow" aria-busy="true" aria-label="Chargement des produits">
	{#each { length: count }, i (i)}
		<div>
			<div class="placeholder w-100 rounded-3 mb-4" style="aspect-ratio: 3/4;"></div>
			<span class="placeholder col-8 mb-2"></span>
			<span class="placeholder col-4"></span>
		</div>
	{/each}
</div>
