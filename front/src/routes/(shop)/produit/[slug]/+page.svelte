<script lang="ts">
	// Port of the Sylius product page (product/show) with the Yack hooks: pricing notice per
	// profile, add-to-cart for ECOM only, "Espace SAV" box. Reviews / ratings are not ported.
	import { enhance } from '$app/forms';
	import Breadcrumbs from '#lib/catalog/Breadcrumbs.svelte';
	import LatestProducts from '#lib/catalog/LatestProducts.svelte';
	import PricingNotice from '#lib/catalog/PricingNotice.svelte';
	import { formatPrice } from '#lib/format.js';

	let { data, form } = $props();
	const product = $derived(data.product);
	const viewer = $derived(data.viewer);

	let imageIndex = $state(0);
	let attributesOpen = $state(false);
	let detailsOpen = $state(true);
	let submitting = $state(false);

	const image = $derived(product.images[imageIndex] ?? product.images[0] ?? null);
	const kicker = $derived([product.brand, product.range].filter(Boolean).join(' · '));

	// Back to the first image when navigating to another product.
	$effect(() => {
		void product.code;
		imageIndex = 0;
	});
</script>

<svelte:head>
	<title>{product.name} | Clik&amp;Yack</title>
	{#if product.shortDescription}<meta name="description" content={product.shortDescription} />{/if}
</svelte:head>

<div class="container mt-4 mb-5">
	<Breadcrumbs items={data.breadcrumb} />
	<div class="row g-3 g-lg-5 mb-6">
		<div class="col-12 col-lg-5 col-xl-4 order-lg-1">
			<div class="sticky-top pt-2" style="top: 3rem;" data-test-product-box>
				<div class="mb-4">
					{#if kicker}<div class="small text-uppercase fw-semibold text-body-secondary mb-1" data-test-product-brand>{kicker}</div>{/if}
					<h1 class="h2 text-break" data-test-product-name={product.name}>{product.name}</h1>
				</div>

				{#if viewer.canSeePrices}
					{#if product.price !== null}
						<div class="mb-3 pb-1">
							<div class="mb-2">
								<div class="d-flex gap-3 align-items-center">
									<div class="fs-3" data-test-product-price>{formatPrice(product.price)}</div>
									{#if product.originalPrice !== null && product.originalPrice > product.price}
										<del class="text-body-tertiary">{formatPrice(product.originalPrice)}</del>
									{/if}
								</div>
							</div>
							{#if product.stockLabel}
								<span class="badge {product.inStock ? 'text-bg-success' : 'text-bg-secondary'}" data-test-product-stock>{product.stockLabel}</span>
							{/if}
						</div>
					{/if}
				{:else}
					<PricingNotice {viewer} />
				{/if}

				{#if viewer.canOrder && product.variantCode}
					<div class="my-4">
						<form
							method="POST"
							action="?/addToCart"
							use:enhance={() => {
								submitting = true;
								return async ({ update }) => {
									await update({ reset: false });
									submitting = false;
								};
							}}
						>
							<input type="hidden" name="variant" value={product.variantCode} />
							<div class="mb-4">
								<div class="field mb-3 required">
									<label for="quantity" class="form-label required">Quantité</label>
									<input type="number" id="quantity" name="quantity" required min="1" step="1" value="1" class="form-control" data-test-quantity />
								</div>
							</div>
							<div class="mb-3">
								<button type="submit" class="btn btn-primary" disabled={submitting} data-test-button="add-to-cart-button">Ajouter au panier</button>
							</div>
						</form>
						{#if form?.error}
							<div class="alert alert-danger" role="alert">{form.error}</div>
						{:else if form?.added}
							<div class="alert alert-success d-flex justify-content-between align-items-center gap-3" role="status" data-test-added-to-cart>
								<span>{form.added > 1 ? `${form.added} articles ajoutés` : 'Article ajouté'} au panier.</span>
								<a class="btn btn-sm btn-outline-success text-nowrap" href="/panier">Voir le panier</a>
							</div>
						{/if}
					</div>
				{/if}

				{#if product.shortDescription}<div class="mb-3">{product.shortDescription}</div>{/if}
				<small class="text-body-tertiary">{product.code}</small>

				<div class="border rounded-3 p-3 mt-4 d-flex align-items-center justify-content-between gap-3">
					<div>
						<div class="fw-bold">Une pièce détachée pour cet appareil ?</div>
						<div class="small text-body-secondary">Saisissez le numéro de série de l'appareil pour retrouver ses vues éclatées et ses pièces d'origine.</div>
					</div>
					<a class="btn btn-outline-primary text-nowrap" href="/sav">Espace SAV</a>
				</div>
			</div>
		</div>

		<div class="col-12 col-lg-7 col-xl-8">
			<div class="row mb-5">
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

			<div class="accordion accordion-flat">
				<div class="accordion-item">
					<h2 class="accordion-header">
						<button class="accordion-button" class:collapsed={!detailsOpen} type="button" aria-expanded={detailsOpen} aria-controls="details" onclick={() => (detailsOpen = !detailsOpen)}>
							<span class="h5 mb-0 py-2">Détails</span>
						</button>
					</h2>
					<div id="details" class="accordion-collapse collapse" class:show={detailsOpen}>
						<div class="accordion-body" data-test-product-details>{product.description ?? ''}</div>
					</div>
				</div>
				{#if product.attributes.length > 0}
					<div class="accordion-item">
						<h2 class="accordion-header">
							<button class="accordion-button" class:collapsed={!attributesOpen} type="button" aria-expanded={attributesOpen} aria-controls="attributes" onclick={() => (attributesOpen = !attributesOpen)}>
								<span class="h5 mb-0 py-2"><span data-test-tab="attributes">Attributs</span></span>
							</button>
						</h2>
						<div id="attributes" class="accordion-collapse collapse" class:show={attributesOpen}>
							<div class="accordion-body">
								<table class="table table-lg table-list" data-test-product-attributes>
									<tbody>
										{#each product.attributes as attribute (attribute.code)}
											<tr>
												<th scope="row">{attribute.name}</th>
												<td>{attribute.value}</td>
											</tr>
										{/each}
									</tbody>
								</table>
							</div>
						</div>
					</div>
				{/if}
			</div>
		</div>
	</div>
</div>

<LatestProducts products={data.latest} {viewer} />
