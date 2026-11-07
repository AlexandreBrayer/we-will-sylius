<script lang="ts">
	// produit-achat (mandatory block of a product template): a card with the states of the product
	// card. Visitor and PRO: PricingNotice (log in / create an account; order access request, open or
	// pending). ECOM: stock tag (`stock.level` of the API), price, quantity + "Ajouter au panier"
	// (remote form, works without JS; the header counter follows through the refreshed queries).
	// Product not sold online (`soldOnline` false, rule of the API): the mention and a link to the
	// contact page, for every profile.
	import PricingNotice from '#lib/catalog/PricingNotice.svelte';
	import { stepQuantity } from '#lib/catalog/quantity.js';
	import StockTag from '#lib/catalog/StockTag.svelte';
	import { formatPrice } from '#lib/format.js';
	import Icon from '#lib/layout/Icon.svelte';
	import { addToCart } from '#lib/remote/cart.remote.js';
	import { getYackContext } from '../context.js';
	import EditorNote from './EditorNote.svelte';
	import ProductFrame from './ProductFrame.svelte';

	const ctx = getYackContext();
	const product = $derived(ctx.product?.product ?? null);
</script>

{#if product}
	<ProductFrame side>
		<div class="yack-buybox" data-yack-block="produit-achat" data-test-buybox={ctx.viewer.profile}>
			{#if !product.soldOnline}
				<div class="yack-buybox__head" data-test-product-not-sold-online>
					<div>
						<div class="yack-buybox__label">Non commercialisé en ligne</div>
						<p class="mb-0">Ce produit ne se commande pas sur le site : contactez votre agence Yack.</p>
					</div>
				</div>
				<a class="btn btn-secondary btn-sm align-self-start" href="/contact">Nous contacter</a>
			{:else if ctx.viewer.canSeePrices}
				{#if product.price !== null}
					<div class="yack-buybox__head">
						<div>
							<div class="yack-buybox__label">Prix HT</div>
							<div class="yack-buybox__price">
								<span data-test-product-price>{formatPrice(product.price)}</span>
								{#if product.originalPrice !== null && product.originalPrice > product.price}
									<del>{formatPrice(product.originalPrice)}</del>
								{/if}
							</div>
						</div>
						{#if product.stock}<StockTag stock={product.stock} />{/if}
					</div>
				{/if}
			{:else}
				<PricingNotice viewer={ctx.viewer} framed={false} />
			{/if}

			{#if product.soldOnline && ctx.viewer.canOrder && product.variantCode}
				<!-- One form instance per variant: its result does not follow the visitor to another product. -->
				{@const cart = addToCart.for(product.variantCode)}
				{@const error = cart.fields.allIssues()?.[0]?.message}
				<!-- Enhanced submission keeps the typed quantity (the default one resets the form). -->
				<form class="yack-buybox__actions" {...cart.enhance(async ({ submit }) => void (await submit()))}>
					<input {...cart.fields.variant.as('hidden', product.variantCode)} />
					<label for="quantity" class="visually-hidden">Quantité</label>
					<div class="yack-qty">
						<button type="button" aria-label="Diminuer la quantité" onclick={(event) => stepQuantity(event, -1)}><Icon name="minus" /></button>
						<input {...cart.fields.quantity.as('number', 1)} id="quantity" required min="1" step="1" data-test-quantity />
						<button type="button" aria-label="Augmenter la quantité" onclick={(event) => stepQuantity(event, 1)}><Icon name="plus" /></button>
					</div>
					<button type="submit" class="btn btn-primary yack-btn-icon" disabled={cart.pending > 0} data-test-button="add-to-cart-button">
						<span class="yack-btn-icon__dot"><Icon name="cart" /></span>Ajouter au panier
					</button>
				</form>
				{#if error}
					<div class="alert alert-danger mb-0" role="alert">{error}</div>
				{:else if cart.result}
					{@const added = cart.result.added}
					<p class="yack-buybox__added" role="status" data-test-added-to-cart>
						<span>{added > 1 ? `${added} articles ajoutés` : 'Article ajouté'} au panier.</span>
						<a class="btn btn-sm btn-light text-nowrap" href="/panier">Voir le panier</a>
					</p>
				{/if}
			{/if}
		</div>
	</ProductFrame>
{:else}
	<EditorNote label="Achat (prix, stock, panier)" text="Choisissez un produit d'exemple pour l'aperçu." />
{/if}
