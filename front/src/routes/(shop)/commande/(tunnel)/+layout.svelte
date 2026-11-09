<script lang="ts">
	// Checkout layout (Sylius checkout/common): stepper, current step on the left, order summary on
	// the right (lines and Yack totals: `getCheckout(step)`, refreshed by a payment method change).
	import { page } from '$app/state';
	import { formatPrice } from '#lib/format.js';
	import Progress from '#lib/layout/Progress.svelte';
	import { ORDER_STEPS, orderStepPosition, routeStep } from '#lib/order/checkout.js';
	import OrderTotals from '#lib/order/OrderTotals.svelte';
	import { getCheckout } from '#lib/remote/checkout.remote.js';

	let { children } = $props();
	const step = $derived(routeStep(page.route.id));
	const checkout = $derived(await getCheckout(step));
	const order = $derived(checkout.order);
	// The summary page has its own right column (totals, terms of sale, order button).
	const sidebar = $derived(step !== 'complete');
</script>

<div class="container mt-4 mb-5">
	<Progress steps={ORDER_STEPS} active={orderStepPosition(step)} ariaLabel="Étapes de la commande" />

	<div class="row g-4 g-lg-5">
		<div class={sidebar ? 'col-12 col-lg-8' : 'col-12'}>
			{@render children()}
		</div>
		{#if sidebar}
			<div class="col-12 col-lg-4">
				<aside class="yack-summary" data-test-checkout-summary>
					<h2 class="yack-summary__title">Votre commande</h2>
					<ul class="yack-summary__lines" data-test-checkout-subtotal>
						{#each checkout.lines as line (line.id)}
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
					<OrderTotals totals={order.yack.totals} escompte={order.yack.escompte} shippingLabel={order.checkoutState === 'cart' || order.checkoutState === 'addressed' ? 'Livraison estimée (HT)' : 'Livraison (HT)'} showShipping={order.shipments.length > 0} />
					{#if order.yack.paymentMethod && order.checkoutState !== 'cart' && order.checkoutState !== 'addressed'}
						<p class="yack-summary__meta" data-test-checkout-summary-payment>
							Paiement : {order.yack.paymentMethod.name}{order.yack.paymentMethod.escompteLabel ? ` (${order.yack.paymentMethod.escompteLabel})` : ''}
						</p>
					{/if}
				</aside>
			</div>
		{/if}
	</div>
</div>
