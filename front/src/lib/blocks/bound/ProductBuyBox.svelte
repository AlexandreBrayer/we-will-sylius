<script lang="ts">
	// produit-achat (mandatory block of a product template): a card with the states of the product
	// card. Visitor and PRO: PricingNotice (log in / create an account; order access request, open or
	// pending). ECOM: stock tag (`stock.level` of the API), price, then quantity + "Ajouter au panier"
	// when in stock (remote form, works without JS; the header counter follows through the refreshed
	// queries), the out of stock notice otherwise.
	// Builder canvas ("Voir comme" ECOM): same form, its submission prevented — no cart call.
	import PricingNotice from '#lib/catalog/PricingNotice.svelte';
	import { stepQuantity } from '#lib/catalog/quantity.js';
	import StockTag from '#lib/catalog/StockTag.svelte';
	import { formatPrice } from '#lib/format.js';
	import Icon from '#lib/layout/Icon.svelte';
	import { addToCart } from '#lib/remote/cart.remote.js';
	import { getYackContext } from '../context.js';
	import EditorNote from './EditorNote.svelte';

	const ctx = getYackContext();
	const product = $derived(ctx.product?.product ?? null);
</script>

{#if product}
	<div class="yack-buybox" data-yack-block="produit-achat" data-test-buybox={ctx.viewer.profile}>
		{#if ctx.viewer.canSeePrices}
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

		{#if ctx.viewer.canOrder && !(product.inStock && product.variantCode)}
			<div data-test-out-of-stock>
				<p class="yack-buybox__lock">Rupture de stock</p>
				<p class="yack-buybox__text">Ce produit ne peut pas être commandé pour le moment.</p>
			</div>
		{:else if ctx.viewer.canOrder && product.variantCode}
			<!-- One form instance per variant: its result does not follow the visitor to another product. -->
			{@const cart = addToCart.for(product.variantCode)}
			{@const error = cart.fields.allIssues()?.[0]?.message}
			<!-- Enhanced submission keeps the typed quantity (the default one resets the form). -->
			<form
				class="yack-buybox__actions"
				{...ctx.editor
					? { onsubmit: (event: SubmitEvent) => event.preventDefault() }
					: cart.enhance(async ({ submit }) => void (await submit()))}
			>
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
{:else}
	<EditorNote label="Achat (prix, stock, panier)" text="Choisissez un produit d'exemple pour l'aperçu." />
{/if}
