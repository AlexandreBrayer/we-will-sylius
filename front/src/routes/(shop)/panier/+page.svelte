<script lang="ts">
	// Port of the Sylius cart page (cart/index) with the Yack totals (escompte, HT / TVA / TTC).
	// Quantities are saved on change (JS) or with "Mettre à jour le panier" (no JS).
	import { enhance } from '$app/forms';
	import { formatPrice } from '#lib/format.js';
	import OrderTotals from '#lib/order/OrderTotals.svelte';
	import { getViewer } from '#lib/remote/session.remote.js';

	let { data, form } = $props();
	const viewer = $derived(await getViewer());
	const cart = $derived(data.cart);

	let submitting = $state(false);
	let cartForm = $state<HTMLFormElement>();
</script>

<svelte:head>
	<title>Panier | Clik&amp;Yack</title>
</svelte:head>

<div class="container mt-4 mb-5">
	<div class="mb-5">
		<h1>Votre panier</h1>
		{#if cart}<div>Modifiez vos articles puis passez commande.</div>{/if}
	</div>

	{#if !viewer.canOrder}
		{#if viewer.canRequestOrderAccess && viewer.orderAccessRequest?.status === 'pending'}
			<div class="alert alert-warning" role="note" data-test-yack-cart-notice="pro" data-test-yack-order-access-cta="pending">
				<div class="fw-bold">Demande d'ouverture à la commande en cours</div>
				<div>
					Envoyée le {new Date(viewer.orderAccessRequest.createdAt).toLocaleDateString('fr-FR')} : le panier et les prix apparaîtront dès sa validation par Yack.
					<a href="/compte/acces-commande">Suivre ma demande</a>
				</div>
			</div>
		{:else}
			<div class="card border-primary" data-test-yack-cart-notice={viewer.profile}>
				<div class="card-body">
					<div class="fw-bold mb-1">La commande en ligne est réservée aux comptes ECOM</div>
					<p class="mb-3">
						Votre compte professionnel vous donne accès au catalogue et à l'espace SAV, sans prix ni panier.
						{#if viewer.canRequestOrderAccess}Demandez l'ouverture de votre compte à la commande : prix, disponibilités et commande directe sur Clik&amp;Yack.{/if}
					</p>
					{#if viewer.canRequestOrderAccess}
						<a class="btn btn-primary" href="/compte/acces-commande">Demander l'ouverture à la commande</a>
					{/if}
					<a class="btn btn-outline-primary" href="/produits">Voir le catalogue</a>
				</div>
			</div>
		{/if}
	{:else if !cart}
		<div class="alert alert-info" data-test-cart-empty>
			{form?.removed ? `« ${form.removed} » a été retiré du panier. ` : ''}Votre panier est vide.
		</div>
		<div class="d-flex gap-2">
			<a class="btn btn-primary" href="/produits">Clik&amp;Yack — produits</a>
			<a class="btn btn-outline-primary" href="/pieces">Clik&amp;Yack Parts — pièces détachées</a>
		</div>
	{:else}
		<div class="row">
			<div class="col-12 col-xl-8 mb-4 position-relative">
				{#if form?.error}
					<div class="alert alert-danger" role="alert">{form.error}</div>
				{:else if form?.removed}
					<div class="alert alert-success" role="status" data-test-cart-removed>« {form.removed} » a été retiré du panier.</div>
				{:else if form?.updated}
					<div class="alert alert-success" role="status" data-test-cart-updated>Panier mis à jour.</div>
				{/if}
				<form
					method="POST"
					action="?/update"
					bind:this={cartForm}
					use:enhance={() => {
						submitting = true;
						return async ({ update }) => {
							await update({ reset: false });
							submitting = false;
						};
					}}
				>
					<div class="table-responsive">
						<table class="table align-middle" data-test-cart-items>
							<thead>
								<tr>
									<th>Article</th>
									<th class="text-end">Prix unitaire HT</th>
									<th style="width: 7rem;">Quantité</th>
									<th class="text-end">Total HT</th>
									<th><span class="visually-hidden">Retirer</span></th>
								</tr>
							</thead>
							<tbody>
								{#each cart.lines as line (line.id)}
									<tr data-test-cart-item={line.code}>
										<td>
											<div class="d-flex align-items-center gap-3">
												<div class="flex-shrink-0 bg-light rounded-3 overflow-hidden" style="width: 4.5rem; aspect-ratio: 1;">
													{#if line.image}<img src={line.image} alt="" class="w-100 h-100 object-fit-cover" />{/if}
												</div>
												<div>
													{#if line.url}
														<a class="link-reset fw-semibold" href={line.url} data-test-cart-item-name>{line.name}</a>
													{:else}
														<span class="fw-semibold" data-test-cart-item-name>{line.name}</span>
													{/if}
													<div class="small text-body-tertiary">
														{line.code}
														{#if line.typeLabel}
															<span class="badge {line.type === 'part' ? 'text-bg-info' : 'text-bg-light border'} ms-1" data-test-cart-item-type={line.type}>{line.typeLabel}</span>
														{/if}
													</div>
												</div>
											</div>
										</td>
										<td class="text-end text-nowrap">{formatPrice(line.unitPrice)}</td>
										<td>
											<label class="visually-hidden" for="quantity-{line.id}">Quantité de {line.name}</label>
											<input
												class="form-control"
												type="number"
												id="quantity-{line.id}"
												name="quantity-{line.id}"
												min="0"
												max="9999"
												step="1"
												required
												value={line.quantity}
												onchange={() => cartForm?.requestSubmit()}
												data-test-cart-item-quantity
											/>
										</td>
										<td class="text-end text-nowrap" data-test-cart-item-total>{formatPrice(line.subtotal)}</td>
										<td>
											<button
												class="btn btn-sm btn-transparent px-2"
												type="submit"
												formaction="?/remove"
												formnovalidate
												name="item"
												value={line.id}
												aria-label="Retirer {line.name}"
												disabled={submitting}
												data-test-remove-cart-item
											>
												<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
													<path d="M18 6l-12 12"></path>
													<path d="M6 6l12 12"></path>
												</svg>
											</button>
										</td>
									</tr>
								{/each}
							</tbody>
						</table>
					</div>
					<div class="d-flex flex-wrap gap-2 justify-content-between">
						<a class="btn btn-link link-reset px-0" href="/produits">← Continuer mes achats</a>
						<button class="btn btn-outline-primary" type="submit" disabled={submitting} data-test-cart-update>Mettre à jour le panier</button>
					</div>
				</form>
			</div>
			<div class="col-12 col-xl-4 ps-xl-5 mb-4">
				<div class="p-4 bg-light mb-4 rounded-3" data-test-cart-summary>
					<h3 class="mb-4">Récapitulatif</h3>
					<OrderTotals totals={cart.totals} escompte={cart.escompte} shippingLabel="Livraison estimée (HT)" showShipping={cart.shipments > 0} />
					<p class="small text-body-secondary mt-3 mb-0">
						Le mode de paiement choisi à la commande peut ouvrir droit à un escompte, déduit du montant HT avant TVA.
					</p>
				</div>
				<div class="d-grid">
					<a class="btn btn-primary" href="/commande/adresse" data-test-cart-checkout-button>Commander</a>
				</div>
			</div>
		</div>
	{/if}
</div>
