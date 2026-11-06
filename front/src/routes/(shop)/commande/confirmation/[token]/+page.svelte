<script lang="ts">
	// Port of the Yack thank-you page (templates/shop/yack/order/thank_you/*): order number, e-mail sent,
	// payment terms (bank transfer: IBAN, reference, due date; card: payment accepted), order summary.
	import { formatPrice } from '#lib/format.js';
	import Progress from '#lib/layout/Progress.svelte';
	import { CHECKOUT_STEPS } from '#lib/order/checkout.js';
	import OrderTotals from '#lib/order/OrderTotals.svelte';
	import PaymentInstruction from '#lib/order/PaymentInstruction.svelte';

	let { data } = $props();
	const order = $derived(data.order);
</script>

<svelte:head>
	<title>Commande n° {order.number} confirmée | Clik&amp;Yack</title>
</svelte:head>

<div class="container mt-4 mb-5">
	<Progress steps={CHECKOUT_STEPS} active={CHECKOUT_STEPS.length} ariaLabel="Étapes de la commande" linksEnabled={false} />

	<div class="text-center mb-5">
		<h1 class="h2" data-test-thank-you>Merci pour votre commande !</h1>
		<p class="mb-1">Votre commande <strong data-test-order-number>n° {order.number}</strong> est enregistrée.</p>
		<p class="text-body-secondary">Un e-mail de confirmation a été envoyé à {order.customer?.email ?? data.viewer.email}.</p>
	</div>

	<div class="row">
		<div class="col-lg-8 mx-auto">
			<PaymentInstruction instruction={order.yack.paymentInstruction} methodName={order.yack.paymentMethod?.name ?? null} />

			<div class="p-4 bg-light rounded-3 my-4" data-test-confirmation-summary>
				<h2 class="h5 mb-3">Votre commande</h2>
				<table class="table mb-3">
					<tbody>
						{#each data.lines as line (line.id)}
							<tr>
								<td class="ps-0">
									<div>{line.name}</div>
									<div class="small text-body-tertiary">{line.code}{line.typeLabel ? ` · ${line.typeLabel}` : ''}</div>
								</td>
								<td class="text-end text-nowrap">× {line.quantity}</td>
								<td class="text-end text-nowrap pe-0">{formatPrice(line.subtotal)}</td>
							</tr>
						{/each}
					</tbody>
				</table>
				<OrderTotals totals={order.yack.totals} escompte={order.yack.escompte} showShipping={order.shipments.length > 0} />
				<div class="small text-body-secondary mt-3">
					{#if order.yack.shipments[0]?.method}Livraison : {order.yack.shipments[0].method.name}<br />{/if}
					{#if order.yack.paymentMethod}Paiement : {order.yack.paymentMethod.name}{/if}
				</div>
			</div>

			<div class="d-flex flex-wrap gap-2 justify-content-center">
				<a class="btn btn-primary" href="/compte/commandes/{order.number}" data-test-order-link>Suivre ma commande</a>
				<a class="btn btn-outline-primary" href="/produits">Continuer mes achats</a>
			</div>
		</div>
	</div>
</div>
