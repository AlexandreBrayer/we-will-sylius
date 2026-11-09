<script lang="ts">
	// Checkout step Validation (Sylius checkout/complete): every line, addresses, delivery, payment with its
	// escompte and term (« Virement à échéance (30 jours) »), Yack totals (HT, escompte, shipping, VAT, TTC),
	// terms of sale to accept. Card: saved card (one click) or a new card typed on the provider's secure
	// page after the order is placed.
	import { formatPrice } from '#lib/format.js';
	import Icon from '#lib/layout/Icon.svelte';
	import { paymentTerm } from '#lib/order/checkout.js';
	import OrderTotals from '#lib/order/OrderTotals.svelte';
	import type { OrderAddress } from '#lib/order/types.js';
	import { getCheckout, getCheckoutCards, placeOrder } from '#lib/remote/checkout.remote.js';

	const checkout = $derived(await getCheckout('complete'));
	const order = $derived(checkout.order);
	const shipment = $derived(order.yack.shipments[0] ?? null);
	const payment = $derived(order.yack.paymentMethod);
	const term = $derived(payment ? paymentTerm(payment) : null);
	const error = $derived(placeOrder.fields.allIssues()?.[0]?.message);
	// Card: saved cards still valid, for a one-click payment (otherwise the card is typed on the provider's page).
	const cards = $derived(payment?.card ? (await getCheckoutCards()).filter((card) => !card.expired) : []);

	const countries = new Intl.DisplayNames(['fr'], { type: 'region' });
</script>

<svelte:head>
	<title>Validation | Commande | Clik&amp;Yack</title>
</svelte:head>

{#snippet address(title: string, value: OrderAddress | null)}
	<div class="col-12 col-md-6">
		<div class="yack-recap-card">
			<div class="yack-recap-card__head">
				<h2>{title}</h2>
				<a href="/commande/adresse">Modifier</a>
			</div>
			{#if value}
				<address>
					<strong>{value.firstName} {value.lastName}</strong><br />
					{#if value.company}{value.company}<br />{/if}
					{value.street}<br />
					{value.postcode} {value.city}<br />
					{countries.of(value.countryCode) ?? value.countryCode}
					{#if value.phoneNumber}<br />{value.phoneNumber}{/if}
				</address>
			{/if}
		</div>
	</div>
{/snippet}

<div class="yack-page-head">
	<h1>Validation de votre commande</h1>
	<p>Vérifiez votre commande avant de la valider.</p>
</div>

<div class="row g-4 g-lg-5">
	<div class="col-12 col-lg-8">
		<div class="row g-3 mb-4" data-test-checkout-addresses>
			{@render address('Adresse de livraison', order.shippingAddress)}
			{@render address('Adresse de facturation', order.billingAddress)}
			{#if shipment?.method}
				<div class="col-12 col-md-6">
					<div class="yack-recap-card">
						<div class="yack-recap-card__head">
							<h2>Livraison</h2>
							<a href="/commande/livraison">Modifier</a>
						</div>
						<div data-test-checkout-shipping-method>{shipment.method.name}</div>
					</div>
				</div>
			{/if}
			{#if payment}
				<div class="col-12 col-md-6">
					<div class="yack-recap-card">
						<div class="yack-recap-card__head">
							<h2>Paiement</h2>
							<a href="/commande/paiement">Modifier</a>
						</div>
						<div class="d-flex flex-wrap gap-2 align-items-center" data-test-checkout-payment-method={payment.code}>
							<span>{payment.name}{#if term}{' '}<span data-test-checkout-payment-term>({term})</span>{/if}</span>
							{#if payment.escompteLabel}
								<span class="yack-tag yack-tag--secondary">{payment.escompteLabel}</span>
							{:else}
								<span class="yack-tag yack-tag--muted">Prix normal</span>
							{/if}
						</div>
					</div>
				</div>
			{/if}
		</div>

		<div class="yack-panel">
			<div class="table-responsive">
				<table class="table yack-lines align-middle" data-test-checkout-items>
					<thead>
						<tr>
							<th>Article</th>
							<th class="text-end">Prix unitaire HT</th>
							<th class="text-end">Quantité</th>
							<th class="text-end">Total HT</th>
						</tr>
					</thead>
					<tbody>
						{#each checkout.lines as line (line.id)}
							<tr data-test-checkout-item={line.code}>
								<td>
									<div class="yack-line">
										<span class="yack-thumb yack-thumb--sm">{#if line.image}<img src={line.image} alt="" />{/if}</span>
										<div>
											<div class="yack-line__name">{line.name}</div>
											<div class="yack-line__meta">
												{line.code}
												{#if line.typeLabel}<span class="yack-tag {line.type === 'part' ? 'yack-tag--secondary' : 'yack-tag--muted'}">{line.typeLabel}</span>{/if}
											</div>
										</div>
									</div>
								</td>
								<td class="text-end text-nowrap">{formatPrice(line.unitPrice)}</td>
								<td class="text-end">{line.quantity}</td>
								<td class="text-end text-nowrap fw-bold">{formatPrice(line.subtotal)}</td>
							</tr>
						{/each}
					</tbody>
				</table>
			</div>
		</div>
		<div class="yack-step-nav">
			<a class="btn btn-ghost" href="/panier">‹ Modifier le panier</a>
		</div>
	</div>

	<div class="col-12 col-lg-4">
		<aside class="yack-summary" data-test-checkout-summary>
			<h2 class="yack-summary__title">Votre commande</h2>
			<OrderTotals totals={order.yack.totals} escompte={order.yack.escompte} showShipping={order.shipments.length > 0} />

			{#if error}
				<div class="alert alert-danger mt-4 mb-0" role="alert" data-test-checkout-error>{error}</div>
			{/if}

			<form class="mt-4" {...placeOrder.enhance(async ({ submit }) => void (await submit()))}>
				<div class="mb-3">
					<label class="form-label fw-bold" for="notes">Commentaire (facultatif)</label>
					<textarea class="form-control" id="notes" {...placeOrder.fields.notes.as('text')} rows="3" placeholder="Informations de livraison, n° de chantier…"></textarea>
				</div>
				{#if payment?.card}
					<fieldset class="mb-3" data-test-card-choice>
						<legend class="form-label fw-bold fs-6 mb-2">Carte bancaire</legend>
						{#each cards as card, index (card.id)}
							<div class="form-check">
								<input class="form-check-input" id="saved-card-{card.id}" {...placeOrder.fields.savedCard.as('radio', String(card.id), index === 0)} data-test-saved-card={card.id} />
								<label class="form-check-label" for="saved-card-{card.id}">Payer avec {card.label} <span class="text-muted small">exp. {card.expiry}</span></label>
							</div>
						{/each}
						<div class="form-check">
							<input class="form-check-input" id="saved-card-new" {...placeOrder.fields.savedCard.as('radio', 'new', cards.length === 0)} data-test-saved-card="new" />
							<label class="form-check-label" for="saved-card-new">{cards.length > 0 ? 'Une autre carte' : 'Saisir ma carte'} <span class="text-muted small">sur la page de paiement sécurisée</span></label>
						</div>
					</fieldset>
				{/if}
				<div class="form-check mb-4">
					<input class="form-check-input" id="cgv" {...placeOrder.fields.cgv.as('checkbox')} required data-test-cgv />
					<label class="form-check-label" for="cgv">J'ai lu et j'accepte les conditions générales de vente de Yack.</label>
				</div>
				<div class="d-grid">
					<button class="btn btn-primary yack-btn-icon justify-content-center" type="submit" disabled={placeOrder.pending > 0} data-test-confirmation-button>
						<span class="yack-btn-icon__dot"><Icon name="check" /></span>
						{payment?.card ? 'Valider et payer' : 'Valider la commande'} ({formatPrice(order.yack.totals.total)} TTC)
					</button>
				</div>
			</form>
		</aside>
	</div>
</div>
