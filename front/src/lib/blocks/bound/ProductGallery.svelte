<script lang="ts">
	// produit-galerie: thumbnails on the left (below on mobile), main image whole on white (link to
	// the original), as the product page of the demo « Demain ».
	import { getYackContext } from '../context.js';
	import EditorNote from './EditorNote.svelte';

	const ctx = getYackContext();
	const product = $derived(ctx.product?.product ?? null);

	let imageIndex = $state(0);
	const image = $derived(product ? (product.images[imageIndex] ?? product.images[0] ?? null) : null);

	// Back to the first image when navigating to another product.
	$effect(() => {
		void product?.code;
		imageIndex = 0;
	});
</script>

{#if product}
	<div class="yack-gallery" data-yack-block="produit-galerie">
		{#if product.images.length > 1}
			<ul class="yack-gallery__thumbs">
				{#each product.images as thumbnail, i (thumbnail.large)}
					<li>
						<button
							type="button"
							class="yack-gallery__thumb"
							aria-label="Image {i + 1}"
							aria-current={i === imageIndex ? 'true' : undefined}
							onclick={() => (imageIndex = i)}
						>
							<img src={thumbnail.thumbnail} alt="" loading="lazy" />
						</button>
					</li>
				{/each}
			</ul>
		{/if}
		{#if image}
			<a href={image.large.replace('/sylius_shop_product_large_thumbnail/', '/sylius_original/')} class="yack-gallery__main" target="_blank" rel="noopener">
				<img id="main-image" src={image.large} alt={product.name} data-test-main-image />
			</a>
		{:else}
			<div class="yack-gallery__main" aria-hidden="true"></div>
		{/if}
	</div>
{:else}
	<EditorNote label="Galerie produit" text="Choisissez un produit d'exemple pour l'aperçu." />
{/if}
