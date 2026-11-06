<script lang="ts">
	// Checkout layout (Sylius checkout/common): steps, current step on the left, order summary on
	// the right (lines and Yack totals: `getCheckout(step)`, refreshed by a payment method change).
	import { page } from '$app/state';
	import { formatPrice } from '#lib/format.js';
	import Progress from '#lib/layout/Progress.svelte';
	import { CHECKOUT_STEPS, routeStep, stepPosition } from '#lib/order/checkout.js';
	import OrderTotals from '#lib/order/OrderTotals.svelte';
	import { getCheckout } from '#lib/remote/checkout.remote.js';

	let { children } = $props();
	const step = $derived(routeStep(page.route.id));
	const checkout = $derived(await getCheckout(step));
	const order = $derived(checkout.order);
	// The summary page shows every line in full width.
	const sidebar = $derived(step !== 'complete');
</script>

<div class="container mt-4 mb-5">
	<Progress steps={CHECKOUT_STEPS} active={stepPosition(step)} ariaLabel="Étapes de la commande" />

	<div class="row">
		<div class={sidebar ? 'col-12 col-lg-7 mb-5' : 'col-12'}>
			<div class={sidebar ? 'pe-lg-6' : ''}>
				{@render children()}
			</div>
		</div>
		{#if sidebar}
			<div class="col-12 col-lg-5">
				<div class="p-4 bg-light rounded-3" data-test-checkout-summary>
					<h2 class="h4 mb-4">Votre commande</h2>
					<table class="table mb-3" data-test-checkout-subtotal>
						<tbody>
							{#each checkout.lines as line (line.id)}
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
					<OrderTotals totals={order.yack.totals} escompte={order.yack.escompte} shippingLabel={order.checkoutState === 'cart' || order.checkoutState === 'addressed' ? 'Livraison estimée (HT)' : 'Livraison (HT)'} showShipping={order.shipments.length > 0} />
					{#if order.yack.paymentMethod && order.checkoutState !== 'cart' && order.checkoutState !== 'addressed'}
						<div class="small text-body-secondary mt-3" data-test-checkout-summary-payment>
							Paiement : {order.yack.paymentMethod.name}{order.yack.paymentMethod.escompteLabel ? ` (${order.yack.paymentMethod.escompteLabel})` : ''}
						</div>
					{/if}
				</div>
			</div>
		{/if}
	</div>
</div>
