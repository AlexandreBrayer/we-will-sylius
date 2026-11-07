<script lang="ts">
	// Placeholder of ProductGrid while its products load (client-side navigation, see #lib/layout/Deferred.svelte).
	let { count = 4 }: { count?: number } = $props();
</script>

<ul class="yack-product-grid placeholder-glow" aria-busy="true" aria-label="Chargement des produits">
	{#each { length: count }, i (i)}
		<li class="yack-card" aria-hidden="true">
			<div class="yack-card__top">
				<span class="yack-card__image placeholder"></span>
				<span class="placeholder col-4"></span>
				<span class="placeholder col-10"></span>
			</div>
			<div class="yack-card__foot">
				<span class="placeholder col-6"></span>
				<span class="placeholder col-8"></span>
			</div>
		</li>
	{/each}
</ul>
