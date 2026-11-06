<script lang="ts">
	// Checkout step 4 (Sylius checkout/complete): every line, addresses, delivery, payment with its
	// escompte, Yack totals (HT, escompte, shipping, VAT, TTC), terms of sale to accept.
	import { enhance } from '$app/forms';
	import { formatPrice } from '#lib/format.js';
	import OrderTotals from '#lib/order/OrderTotals.svelte';
	import type { OrderAddress } from '#lib/order/types.js';

	let { data, form } = $props();
	const order = $derived(data.order);
	const shipment = $derived(order.yack.shipments[0] ?? null);
	const payment = $derived(order.yack.paymentMethod);

	let submitting = $state(false);
	const countries = new Intl.DisplayNames(['fr'], { type: 'region' });
</script>

<svelte:head>
	<title>Récapitulatif | Commande | Clik&amp;Yack</title>
</svelte:head>

{#snippet address(title: string, value: OrderAddress | null)}
	<div class="col-12 col-md-6">
		<div class="card h-100">
			<div class="card-body">
				<div class="d-flex justify-content-between mb-2">
					<h2 class="h6 mb-0">{title}</h2>
					<a class="small" href="/commande/adresse">Modifier</a>
				</div>
				{#if value}
					<address class="mb-0">
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
	</div>
{/snippet}

<div class="mb-5">
	<h1>Récapitulatif de votre commande</h1>
	<div>Vérifiez votre commande avant de la valider.</div>
</div>

<div class="row g-3 mb-4" data-test-checkout-addresses>
	{@render address('Adresse de livraison', order.shippingAddress)}
	{@render address('Adresse de facturation', order.billingAddress)}
	{#if shipment?.method}
		<div class="col-12 col-md-6">
			<div class="card h-100">
				<div class="card-body">
					<div class="d-flex justify-content-between mb-2">
						<h2 class="h6 mb-0">Livraison</h2>
						<a class="small" href="/commande/livraison">Modifier</a>
					</div>
					<div data-test-checkout-shipping-method>{shipment.method.name}</div>
				</div>
			</div>
		</div>
	{/if}
	{#if payment}
		<div class="col-12 col-md-6">
			<div class="card h-100">
				<div class="card-body">
					<div class="d-flex justify-content-between mb-2">
						<h2 class="h6 mb-0">Paiement</h2>
						<a class="small" href="/commande/paiement">Modifier</a>
					</div>
					<div data-test-checkout-payment-method={payment.code}>
						{payment.name}
						{#if payment.escompteLabel}
							<span class="badge text-bg-success ms-2">{payment.escompteLabel}</span>
						{:else}
							<span class="badge text-bg-light border ms-2">Prix normal</span>
						{/if}
					</div>
				</div>
			</div>
		</div>
	{/if}
</div>

<div class="row">
	<div class="col-12 col-xl-8 mb-4">
		<div class="table-responsive">
			<table class="table align-middle" data-test-checkout-items>
				<thead>
					<tr>
						<th>Article</th>
						<th class="text-end">Prix unitaire HT</th>
						<th class="text-end">Quantité</th>
						<th class="text-end">Total HT</th>
					</tr>
				</thead>
				<tbody>
					{#each data.lines as line (line.id)}
						<tr data-test-checkout-item={line.code}>
							<td>
								<div class="d-flex align-items-center gap-3">
									<div class="flex-shrink-0 bg-light rounded-3 overflow-hidden" style="width: 3.5rem; aspect-ratio: 1;">
										{#if line.image}<img src={line.image} alt="" class="w-100 h-100 object-fit-cover" />{/if}
									</div>
									<div>
										<div class="fw-semibold">{line.name}</div>
										<div class="small text-body-tertiary">
											{line.code}
											{#if line.typeLabel}<span class="badge {line.type === 'part' ? 'text-bg-info' : 'text-bg-light border'} ms-1">{line.typeLabel}</span>{/if}
										</div>
									</div>
								</div>
							</td>
							<td class="text-end text-nowrap">{formatPrice(line.unitPrice)}</td>
							<td class="text-end">{line.quantity}</td>
							<td class="text-end text-nowrap">{formatPrice(line.subtotal)}</td>
						</tr>
					{/each}
				</tbody>
			</table>
		</div>
		<a class="btn btn-link link-reset px-0" href="/panier">Modifier le panier</a>
	</div>

	<div class="col-12 col-xl-4 ps-xl-5">
		<div class="p-4 bg-light mb-4 rounded-3" data-test-checkout-summary>
			<h2 class="h4 mb-4">Total</h2>
			<OrderTotals totals={order.yack.totals} escompte={order.yack.escompte} showShipping={order.shipments.length > 0} />
		</div>

		{#if form?.error}
			<div class="alert alert-danger" role="alert" data-test-checkout-error>{form.error}</div>
		{/if}

		<form
			method="POST"
			use:enhance={() => {
				submitting = true;
				return async ({ update }) => {
					await update({ reset: false });
					submitting = false;
				};
			}}
		>
			<div class="mb-3">
				<label class="form-label" for="notes">Commentaire (facultatif)</label>
				<textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Informations de livraison, n° de chantier…">{form?.notes ?? ''}</textarea>
			</div>
			<div class="form-check mb-4">
				<input class="form-check-input" type="checkbox" id="cgv" name="cgv" value="1" required data-test-cgv />
				<label class="form-check-label" for="cgv">J'ai lu et j'accepte les conditions générales de vente de Yack.</label>
			</div>
			<div class="d-grid">
				<button class="btn btn-primary btn-lg" type="submit" disabled={submitting} data-test-confirmation-button>
					Valider la commande — {formatPrice(order.yack.totals.total)} TTC
				</button>
			</div>
		</form>
	</div>
</div>
