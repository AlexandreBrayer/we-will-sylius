<script lang="ts">
	// produit-achat (mandatory block of a product template): price and stock for the profiles that
	// may see prices, pricing notice otherwise (visitor: log in / register; PRO: order access
	// request), quantity + "Ajouter au panier" for ECOM accounts (remote form, works without JS).
	import PricingNotice from '#lib/catalog/PricingNotice.svelte';
	import { formatPrice } from '#lib/format.js';
	import { addToCart } from '#lib/remote/cart.remote.js';
	import { getYackContext } from '../context.js';
	import EditorNote from './EditorNote.svelte';
	import ProductFrame from './ProductFrame.svelte';

	const ctx = getYackContext();
	const product = $derived(ctx.product?.product ?? null);
</script>

{#if product}
	<ProductFrame side>
		<div data-yack-block="produit-achat">
			{#if ctx.viewer.canSeePrices}
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
				<PricingNotice viewer={ctx.viewer} />
			{/if}

			{#if ctx.viewer.canOrder && product.variantCode}
				<!-- One form instance per variant: its result does not follow the visitor to another product. -->
				{@const cart = addToCart.for(product.variantCode)}
				{@const error = cart.fields.allIssues()?.[0]?.message}
				<div class="my-4">
					<!-- Enhanced submission keeps the typed quantity (the default one resets the form). -->
					<form {...cart.enhance(async ({ submit }) => void (await submit()))}>
						<input {...cart.fields.variant.as('hidden', product.variantCode)} />
						<div class="mb-4">
							<div class="field mb-3 required">
								<label for="quantity" class="form-label required">Quantité</label>
								<input {...cart.fields.quantity.as('number', 1)} id="quantity" required min="1" step="1" class="form-control" data-test-quantity />
							</div>
						</div>
						<div class="mb-3">
							<button type="submit" class="btn btn-primary" disabled={cart.pending > 0} data-test-button="add-to-cart-button">Ajouter au panier</button>
						</div>
					</form>
					{#if error}
						<div class="alert alert-danger" role="alert">{error}</div>
					{:else if cart.result}
						{@const added = cart.result.added}
						<div class="alert alert-success d-flex justify-content-between align-items-center gap-3" role="status" data-test-added-to-cart>
							<span>{added > 1 ? `${added} articles ajoutés` : 'Article ajouté'} au panier.</span>
							<a class="btn btn-sm btn-outline-success text-nowrap" href="/panier">Voir le panier</a>
						</div>
					{/if}
				</div>
			{/if}
		</div>
	</ProductFrame>
{:else}
	<EditorNote label="Achat (prix, stock, panier)" text="Choisissez un produit d'exemple pour l'aperçu." />
{/if}
