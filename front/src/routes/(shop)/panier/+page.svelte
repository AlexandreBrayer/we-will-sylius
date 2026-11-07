<script lang="ts">
	// Port of the Sylius cart page (cart/index) with the Yack totals (escompte, HT / TVA / TTC), at the
	// charter of the demo: stepper, lines on the left, summary on the right (_checkout.scss).
	// Quantities are saved on change (JS: typed value or − / +) or with "Mettre à jour le panier" (no JS);
	// `updateCart` refreshes the page content and the header count in the same response.
	import { formatPrice } from '#lib/format.js';
	import Icon from '#lib/layout/Icon.svelte';
	import Progress from '#lib/layout/Progress.svelte';
	import { ORDER_STEPS } from '#lib/order/checkout.js';
	import OrderTotals from '#lib/order/OrderTotals.svelte';
	import { getCart, updateCart } from '#lib/remote/cart.remote.js';
	import { getViewer } from '#lib/remote/session.remote.js';

	const viewer = $derived(await getViewer());
	const cart = $derived(await getCart());
	const result = $derived(updateCart.result);
	const error = $derived(updateCart.fields.allIssues()?.[0]?.message);
	const submitting = $derived(updateCart.pending > 0);

	// − / + only act once the page is interactive (without JS: type the quantity, then "Mettre à jour").
	let hydrated = $state(false);
	$effect(() => {
		hydrated = true;
	});

	/** − / + : new quantity in the field, then saved like a typed one. */
	function step(event: MouseEvent, delta: number) {
		const input = (event.currentTarget as HTMLElement).closest('.yack-qty')?.querySelector('input');
		if (!input) return;
		input.value = String(Math.min(9999, Math.max(1, (Number(input.value) || 0) + delta)));
		input.form?.requestSubmit();
	}
</script>

<svelte:head>
	<title>Panier | Clik&amp;Yack</title>
</svelte:head>

<div class="container mt-4 mb-5">
	{#if viewer.canOrder}
		<Progress steps={ORDER_STEPS} active={1} ariaLabel="Étapes de la commande" />
	{/if}

	<div class="yack-page-head">
		<h1>Votre panier</h1>
		{#if cart}<p>Modifiez vos articles puis passez commande.</p>{/if}
	</div>

	{#if !viewer.canOrder}
		{#if viewer.canRequestOrderAccess && viewer.orderAccessRequest?.status === 'pending'}
			<div class="yack-callout yack-callout--pending" role="note" data-test-yack-cart-notice="pro" data-test-yack-order-access-cta="pending">
				<span class="yack-callout__title">Demande d'ouverture à la commande en cours</span>
				Envoyée le {new Date(viewer.orderAccessRequest.createdAt).toLocaleDateString('fr-FR')} : le panier et les prix apparaîtront dès sa validation par Yack.
				<a class="yack-link d-inline-flex" href="/compte/acces-commande">Suivre ma demande <Icon name="chevron-right" /></a>
			</div>
		{:else}
			<div class="yack-empty" data-test-yack-cart-notice={viewer.profile}>
				<span class="yack-round-icon"><Icon name="cart" /></span>
				<h2 class="h4">La commande en ligne est réservée aux comptes ECOM</h2>
				<p>
					Votre compte professionnel vous donne accès au catalogue et à l'espace SAV, sans prix ni panier.
					{#if viewer.canRequestOrderAccess}Demandez l'ouverture de votre compte à la commande : prix, disponibilités et commande directe sur Clik&amp;Yack.{/if}
				</p>
				<div class="yack-actions-center">
					{#if viewer.canRequestOrderAccess}
						<a class="btn btn-primary" href="/compte/acces-commande">Demander l'ouverture à la commande</a>
					{/if}
					<a class="btn btn-ghost" href="/produits">Voir le catalogue</a>
				</div>
			</div>
		{/if}
	{:else if !cart}
		<div class="yack-empty" data-test-cart-empty>
			<span class="yack-round-icon"><Icon name="cart" /></span>
			<h2 class="h4">Votre panier est vide</h2>
			<p>
				{result?.removed ? `« ${result.removed} » a été retiré du panier. ` : ''}Ajoutez des produits depuis le catalogue ou les pièces détachées.
			</p>
			<div class="yack-actions-center">
				<a class="btn btn-primary" href="/produits">Voir nos produits</a>
				<a class="btn btn-ghost" href="/pieces">Pièces détachées</a>
			</div>
		</div>
	{:else}
		<div class="row g-4">
			<div class="col-12 col-lg-8">
				{#if error}
					<div class="alert alert-danger" role="alert">{error}</div>
				{:else if result?.removed}
					<div class="alert alert-success" role="status" data-test-cart-removed>« {result.removed} » a été retiré du panier.</div>
				{:else if result?.updated}
					<div class="alert alert-success" role="status" data-test-cart-updated>Panier mis à jour.</div>
				{/if}
				<!-- Reset after each submission: the inputs take the saved quantities of the refreshed cart. -->
				<form {...updateCart}>
					<!-- Default button (Enter in a quantity): saves the quantities, never removes the first line. -->
					<button type="submit" class="d-none" tabindex="-1" aria-hidden="true"></button>
					<div class="yack-panel">
						<div class="table-responsive">
							<table class="table yack-lines yack-lines--stack align-middle" data-test-cart-items>
								<thead>
									<tr>
										<th>Article</th>
										<th class="text-end">Prix unitaire HT</th>
										<th class="yack-lines__qty">Quantité</th>
										<th class="text-end">Total HT</th>
										<th><span class="visually-hidden">Retirer</span></th>
									</tr>
								</thead>
								<tbody>
									{#each cart.lines as line, i (line.id)}
										<tr data-test-cart-item={line.code}>
											<td class="yack-lines__cell-line">
												<div class="yack-line">
													<span class="yack-thumb">{#if line.image}<img src={line.image} alt="" />{/if}</span>
													<div>
														{#if line.url}
															<a class="yack-line__name" href={line.url} data-test-cart-item-name>{line.name}</a>
														{:else}
															<span class="yack-line__name" data-test-cart-item-name>{line.name}</span>
														{/if}
														<div class="yack-line__meta">
															{line.code}
															{#if line.typeLabel}
																<span class="yack-tag {line.type === 'part' ? 'yack-tag--secondary' : 'yack-tag--muted'}" data-test-cart-item-type={line.type}>{line.typeLabel}</span>
															{/if}
														</div>
													</div>
												</div>
											</td>
											<td class="yack-lines__cell-unit text-end text-nowrap" data-label="Prix unitaire HT">{formatPrice(line.unitPrice)}</td>
											<td class="yack-lines__cell-qty">
												<input {...updateCart.fields.items[i].id.as('hidden', String(line.id))} />
												<label class="visually-hidden" for="quantity-{line.id}">Quantité de {line.name}</label>
												<div class="yack-qty">
													<button type="button" aria-label="Diminuer la quantité de {line.name}" disabled={!hydrated || submitting || line.quantity <= 1} onclick={(event) => step(event, -1)}>
														<Icon name="minus" />
													</button>
													<input
														id="quantity-{line.id}"
														{...updateCart.fields.items[i].quantity.as('number', line.quantity)}
														min="0"
														max="9999"
														step="1"
														required
														onchange={(event) => event.currentTarget.form?.requestSubmit()}
														data-test-cart-item-quantity
													/>
													<button type="button" aria-label="Augmenter la quantité de {line.name}" disabled={!hydrated || submitting} onclick={(event) => step(event, 1)}>
														<Icon name="plus" />
													</button>
												</div>
											</td>
											<td class="yack-lines__cell-total text-end text-nowrap fw-bold" data-test-cart-item-total>{formatPrice(line.subtotal)}</td>
											<td class="yack-lines__cell-remove text-end">
												<button
													class="yack-btn-remove"
													{...updateCart.fields.remove.as('submit', String(line.id))}
													formnovalidate
													aria-label="Retirer {line.name}"
													disabled={submitting}
													data-test-remove-cart-item
												>
													<Icon name="close" />
												</button>
											</td>
										</tr>
									{/each}
								</tbody>
							</table>
						</div>
					</div>
					<div class="yack-cart-actions">
						<a class="btn btn-ghost" href="/produits">‹ Continuer mes achats</a>
						<button class="btn btn-ghost" type="submit" disabled={submitting} data-test-cart-update>Mettre à jour le panier</button>
					</div>
				</form>
			</div>
			<div class="col-12 col-lg-4">
				<aside class="yack-summary" data-test-cart-summary>
					<h2 class="yack-summary__title">Récapitulatif</h2>
					<OrderTotals totals={cart.totals} escompte={cart.escompte} shippingLabel="Livraison estimée (HT)" showShipping={cart.shipments > 0} />
					<div class="d-grid mt-4">
						<a class="btn btn-primary" href="/commande/adresse" data-test-cart-checkout-button>Commander</a>
					</div>
					<p class="yack-summary__note">
						Le mode de paiement choisi à la commande peut ouvrir droit à un escompte, déduit du montant HT avant TVA.
					</p>
				</aside>
			</div>
		</div>
	{/if}
</div>
