<script lang="ts">
	import { formatPrice } from '#lib/format.js';
	import type { ProductSummary, Viewer } from '#lib/types.js';

	let { product, viewer }: { product: ProductSummary; viewer: Viewer } = $props();
</script>

<!-- Same markup as the Sylius shop card (sylius_shop.shared.product.card + Yack pricing notice). -->
<div data-test-product={product.code}>
	<a class="link-reset" href={product.url}>
		<div class="mb-4">
			<div class="overflow-auto bg-light rounded-3" style="aspect-ratio: 3/4;">
				{#if product.image}
					<img class="img-fluid w-100 h-100 object-fit-cover" src={product.image} alt="Produit {product.name}" loading="lazy" />
				{/if}
			</div>
		</div>
		<div class="h6 text-break">{product.name}</div>
	</a>
	{#if viewer.canSeePrices}
		{#if product.price !== null}
			<div>
				{#if product.originalPrice !== null && product.originalPrice > product.price}
					<del class="text-body-tertiary me-1">{formatPrice(product.originalPrice)}</del>
				{/if}
				<span data-test-product-price>{formatPrice(product.price)}</span>
			</div>
		{/if}
	{:else if viewer.canRequestOrderAccess}
		<div class="small mt-1">
			{#if viewer.orderAccessRequest?.status === 'pending'}
				<span class="text-body-tertiary">Demande d'ouverture à la commande en cours</span>
			{:else}
				<a href="/compte/acces-commande" class="link-primary">Commander en ligne : demandez l'ouverture de votre compte</a>
			{/if}
		</div>
	{:else}
		<div class="small text-body-tertiary mt-1">Connectez-vous pour voir les prix</div>
	{/if}
</div>
