<script lang="ts">
	// produit-achat (mandatory block of a product template): a card with the states of the product
	// card. Visitor: prices reserved to e-commerce accounts, log in / create an account. PRO: order
	// access request (open: "Accès e-commerce"; pending: request date). ECOM: stock tag and label of
	// the API, price, quantity + "Ajouter au panier" (remote form, works without JS; the header
	// counter follows through the refreshed queries).
	import { page } from '$app/state';
	import { formatDate, formatPrice } from '#lib/format.js';
	import Icon from '#lib/layout/Icon.svelte';
	import { addToCart } from '#lib/remote/cart.remote.js';
	import { getYackContext } from '../context.js';
	import EditorNote from './EditorNote.svelte';
	import ProductFrame from './ProductFrame.svelte';

	const ctx = getYackContext();
	const product = $derived(ctx.product?.product ?? null);
	const request = $derived(ctx.viewer.orderAccessRequest);

	/** "−" / "+" of the quantity (JavaScript only: without it, the field is typed in). */
	function step(event: MouseEvent, direction: 1 | -1): void {
		const input = (event.currentTarget as HTMLElement).parentElement?.querySelector('input');
		if (!input) return;
		if (direction > 0) input.stepUp();
		else input.stepDown();
		input.dispatchEvent(new Event('input', { bubbles: true }));
	}
</script>

{#if product}
	<ProductFrame side>
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
						{#if product.stockLabel}
							<span class="yack-tag {product.inStock ? 'yack-tag--secondary' : 'yack-tag--muted'}" data-test-product-stock>{product.stockLabel}</span>
						{/if}
					</div>
				{/if}
			{:else if ctx.viewer.canRequestOrderAccess}
				{#if request?.status === 'pending'}
					<p class="yack-buybox__lock" data-test-yack-pricing-notice="pro" data-test-yack-order-access-cta="pending">Demande d'ouverture en cours</p>
					<p class="yack-buybox__text">
						Envoyée le {formatDate(request.createdAt)} : les prix et le panier apparaîtront dès sa validation par Yack.
					</p>
					<div class="yack-buybox__actions">
						<a class="btn btn-secondary" href="/compte/acces-commande">Suivre ma demande</a>
					</div>
				{:else}
					<p class="yack-buybox__lock" data-test-yack-pricing-notice="pro" data-test-yack-order-access-cta="open">Prix et commande réservés aux comptes e-commerce</p>
					<p class="yack-buybox__text">Votre compte pro donne accès à la documentation. Demandez l'accès e-commerce pour voir les prix et commander en ligne.</p>
					<div class="yack-buybox__actions">
						<a class="btn btn-secondary" href="/compte/acces-commande">Accès e-commerce</a>
					</div>
				{/if}
			{:else}
				<p class="yack-buybox__lock" data-test-yack-pricing-notice={ctx.viewer.profile}>Prix réservés aux comptes e-commerce</p>
				<p class="yack-buybox__text">Connectez-vous ou créez votre compte pro pour accéder aux prix et à la commande en ligne.</p>
				<div class="yack-buybox__actions">
					<a class="btn btn-secondary" href="/connexion?redirect={encodeURIComponent(page.url.pathname)}">Se connecter</a>
					<a class="btn btn-light" href="/inscription">Créer un compte</a>
				</div>
			{/if}

			{#if ctx.viewer.canOrder && product.variantCode}
				<!-- One form instance per variant: its result does not follow the visitor to another product. -->
				{@const cart = addToCart.for(product.variantCode)}
				{@const error = cart.fields.allIssues()?.[0]?.message}
				<!-- Enhanced submission keeps the typed quantity (the default one resets the form). -->
				<form class="yack-buybox__actions" {...cart.enhance(async ({ submit }) => void (await submit()))}>
					<input {...cart.fields.variant.as('hidden', product.variantCode)} />
					<label for="quantity" class="visually-hidden">Quantité</label>
					<div class="yack-qty">
						<button type="button" aria-label="Diminuer la quantité" onclick={(event) => step(event, -1)}>−</button>
						<input {...cart.fields.quantity.as('number', 1)} id="quantity" required min="1" step="1" data-test-quantity />
						<button type="button" aria-label="Augmenter la quantité" onclick={(event) => step(event, 1)}>+</button>
					</div>
					<button type="submit" class="btn btn-primary yack-btn-icon" disabled={cart.pending > 0} data-test-button="add-to-cart-button">
						<span class="yack-btn-icon__dot"><Icon name="bag" /></span>Ajouter au panier
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
