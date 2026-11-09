<script lang="ts">
	// Product card of the demo « Demain » (grids, related products, "produits" block), four states.
	// Visitor: prices reserved to e-commerce accounts, "Se connecter", "Voir le produit". PRO: order
	// access request ("Accès e-commerce", or the pending request). ECOM: stock tag (`stock.level`),
	// price, then quantity + "Ajouter au panier" when in stock (remote form per variant, works without
	// JS; the header counter follows through `getCartSummary`), the out of stock notice otherwise.
	import { page } from '$app/state';
	import { formatPrice } from '#lib/format.js';
	import Icon from '#lib/layout/Icon.svelte';
	import { addToCart } from '#lib/remote/cart.remote.js';
	import type { ProductSummary, Viewer } from '#lib/types.js';
	import { stepQuantity } from './quantity.js';
	import StockTag from './StockTag.svelte';

	let { product, viewer }: { product: ProductSummary; viewer: Viewer } = $props();
	const id = $props.id();
	/** The photo did not load (missing file): the empty state is shown instead of a broken image. */
	let broken = $state(false);
	const photo = $derived(broken ? null : product.image);
</script>

<article class="yack-card" data-test-product={product.code}>
	<div class="yack-card__top">
		<a class="yack-card__image" class:yack-card__image--empty={!photo} href={product.url} tabindex="-1" aria-hidden="true">
			{#if photo}
				<img src={photo} alt="" loading="lazy" onerror={() => (broken = true)} />
			{:else}
				<img src="/yack/logo-yack.png" alt="" />
			{/if}
		</a>
		{#if product.brand}<span class="yack-tag yack-tag--tertiary" data-test-product-brand>{product.brand}</span>{/if}
		<a class="yack-card__name text-break" href={product.url}>{product.name}</a>
	</div>

	<div class="yack-card__foot" data-test-product-card-state={viewer.canSeePrices ? 'ecom' : viewer.canRequestOrderAccess ? 'pro' : 'visitor'}>
		{#if viewer.canSeePrices}
			<div class="yack-card__row">
				{#if product.stock}<StockTag stock={product.stock} />{/if}
				{#if product.price !== null}
					<span class="yack-card__price">
						{#if product.originalPrice !== null && product.originalPrice > product.price}<del>{formatPrice(product.originalPrice)}</del>{/if}
						<span data-test-product-price>{formatPrice(product.price)}</span>
						<small>HT</small>
					</span>
				{/if}
			</div>
			{#if viewer.canOrder && !(product.inStock && product.variantCode)}
				<p class="yack-card__lock" data-test-out-of-stock>Commande impossible : rupture de stock</p>
				<div class="yack-card__row">
					<a class="yack-link" href={product.url}>Voir le produit<Icon name="chevron-right" /></a>
				</div>
			{:else if viewer.canOrder && product.variantCode}
				<!-- One form instance per variant: several cards on a page, each its own result. -->
				{@const cart = addToCart.for(product.variantCode)}
				{@const error = cart.fields.allIssues()?.[0]?.message}
				<form class="yack-card__row" {...cart.enhance(async ({ submit }) => void (await submit()))}>
					<input {...cart.fields.variant.as('hidden', product.variantCode)} />
					<label for="{id}-quantity" class="visually-hidden">Quantité de {product.name}</label>
					<div class="yack-qty">
						<button type="button" aria-label="Diminuer la quantité" onclick={(event) => stepQuantity(event, -1)}><Icon name="minus" /></button>
						<input {...cart.fields.quantity.as('number', 1)} id="{id}-quantity" required min="1" step="1" data-test-quantity />
						<button type="button" aria-label="Augmenter la quantité" onclick={(event) => stepQuantity(event, 1)}><Icon name="plus" /></button>
					</div>
					<button type="submit" class="btn btn-primary yack-btn-icon" disabled={cart.pending > 0} data-test-button="add-to-cart-button">
						<span class="yack-btn-icon__dot"><Icon name="cart" /></span>Ajouter au panier
					</button>
				</form>
				{#if error}
					<p class="yack-card__status yack-card__status--error" role="alert">{error}</p>
				{:else if cart.result}
					<p class="yack-card__status" role="status" data-test-added-to-cart>
						{cart.result.added > 1 ? `${cart.result.added} ajoutés` : 'Ajouté'} au panier · <a href="/panier">Voir le panier</a>
					</p>
				{/if}
			{:else}
				<div class="yack-card__row">
					<a class="yack-link" href={product.url}>Voir le produit<Icon name="chevron-right" /></a>
				</div>
			{/if}
		{:else if viewer.canRequestOrderAccess}
			{@const pending = viewer.orderAccessRequest?.status === 'pending'}
			<p class="yack-card__lock">{pending ? "Demande d'ouverture en cours" : 'Documentation technique disponible'}</p>
			<div class="yack-card__row">
				<a class="btn btn-secondary btn-sm" href={product.url}>Voir le produit</a>
				<a class="yack-link" href="/compte/acces-commande" data-test-yack-order-access-cta={pending ? 'pending' : 'open'}>
					{pending ? 'Suivre ma demande' : 'Accès e-commerce'}<Icon name="chevron-right" />
				</a>
			</div>
		{:else}
			<p class="yack-card__lock" data-test-yack-pricing-notice="visitor">Prix réservés aux comptes e-commerce</p>
			<div class="yack-card__row">
				<a class="btn btn-secondary btn-sm" href="/connexion?redirect={encodeURIComponent(page.url.pathname + page.url.search)}">Se connecter</a>
				<a class="yack-link" href={product.url}>Voir le produit<Icon name="chevron-right" /></a>
			</div>
		{/if}
	</div>
</article>
