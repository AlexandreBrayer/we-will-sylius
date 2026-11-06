<script lang="ts">
	// produit-galerie: main image (link to the original) and thumbnails.
	import { getYackContext } from '../context.js';
	import EditorNote from './EditorNote.svelte';
	import ProductFrame from './ProductFrame.svelte';

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
	<ProductFrame>
		<div class="row mb-5" data-yack-block="produit-galerie">
			<div class="col pe-lg-5 pe-xxl-5">
				{#if image}
					<a href={image.large.replace('/sylius_shop_product_large_thumbnail/', '/sylius_original/')} class="d-block overflow-auto bg-light rounded-3" target="_blank" rel="noopener">
						<img id="main-image" src={image.large} alt={product.name} class="img-fluid w-100 h-100 object-fit-cover" data-test-main-image />
					</a>
				{:else}
					<div class="bg-light rounded-3" style="aspect-ratio: 1;"></div>
				{/if}
			</div>
			{#if product.images.length > 1}
				<div class="col-12 mt-3 d-flex flex-wrap gap-2">
					{#each product.images as thumbnail, i (thumbnail.large)}
						<button
							type="button"
							class="btn p-0 border rounded-3 overflow-hidden"
							class:border-primary={i === imageIndex}
							style="width: 5rem; aspect-ratio: 3/4;"
							aria-label="Image {i + 1}"
							onclick={() => (imageIndex = i)}
						>
							<img src={thumbnail.thumbnail} alt="" class="w-100 h-100 object-fit-cover" />
						</button>
					{/each}
				</div>
			{/if}
		</div>
	</ProductFrame>
{:else}
	<EditorNote label="Galerie produit" text="Choisissez un produit d'exemple pour l'aperçu." />
{/if}
