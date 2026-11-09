<script lang="ts">
	// Port of the Yack thank-you page (templates/shop/yack/order/thank_you/*): order number, e-mail sent,
	// payment terms (bank transfer: IBAN, reference, due date; card: payment accepted), order summary.
	// End page, not a step: no stepper (the steps end at Validation).
	import { formatPrice } from '#lib/format.js';
	import Icon from '#lib/layout/Icon.svelte';
	import OrderTotals from '#lib/order/OrderTotals.svelte';
	import PaymentInstruction from '#lib/order/PaymentInstruction.svelte';
	import { getConfirmedOrder } from '#lib/remote/checkout.remote.js';
	import { getViewer } from '#lib/remote/session.remote.js';

	let { params } = $props();
	const viewer = $derived(await getViewer());
	const confirmed = $derived(await getConfirmedOrder(params.token));
	const order = $derived(confirmed.order);
</script>

<svelte:head>
	<title>Commande n° {order.number} confirmée | Clik&amp;Yack</title>
</svelte:head>

<div class="container mt-4 mb-5">
	<div class="row">
		<div class="col-lg-8 mx-auto">
			<div class="yack-confirm mb-4">
				<span class="yack-round-icon yack-round-icon--success"><Icon name="check" /></span>
				<h1 class="h2" data-test-thank-you>Merci pour votre commande !</h1>
				<p class="mb-1">Votre commande <strong data-test-order-number>n° {order.number}</strong> est enregistrée.</p>
				<p class="mb-0">Un e-mail de confirmation a été envoyé à {order.customer?.email ?? viewer.email}.</p>
			</div>

			<PaymentInstruction instruction={order.yack.paymentInstruction} methodName={order.yack.paymentMethod?.name ?? null} />

			<div class="yack-summary my-4" data-test-confirmation-summary>
				<h2 class="yack-summary__title">Votre commande</h2>
				<ul class="yack-summary__lines">
					{#each confirmed.lines as line (line.id)}
						<li class="yack-summary__line">
							<span class="yack-summary__name">
								{line.name}
								<small>{line.code}{line.typeLabel ? ` · ${line.typeLabel}` : ''}</small>
							</span>
							<span class="yack-summary__qty">× {line.quantity}</span>
							<span class="yack-summary__amount">{formatPrice(line.subtotal)}</span>
						</li>
					{/each}
				</ul>
				<OrderTotals totals={order.yack.totals} escompte={order.yack.escompte} showShipping={order.shipments.length > 0} />
				<p class="yack-summary__meta">
					{#if order.yack.shipments[0]?.method}Livraison : {order.yack.shipments[0].method.name}<br />{/if}
					{#if order.yack.paymentMethod}Paiement : {order.yack.paymentMethod.name}{/if}
				</p>
			</div>

			<div class="yack-actions-center">
				<a class="btn btn-primary" href="/compte/commandes/{order.number}" data-test-order-link>Suivre ma commande</a>
				<a class="btn btn-ghost" href="/produits">Continuer mes achats</a>
			</div>
		</div>
	</div>
</div>
