<script lang="ts">
	// Payment / shipping status of an order as a charter tag (.yack-tag--*, _components.scss): done in
	// turquoise, waiting in yellow, the rest in grey. The French label comes from the API
	// (yack.paymentStatus / yack.shippingStatus).
	import type { OrderState } from './types.js';

	let { kind, status }: { kind: 'payment' | 'shipping'; status: OrderState } = $props();

	const CLASSES: Record<'payment' | 'shipping', Record<string, string>> = {
		payment: {
			paid: 'yack-tag--secondary',
			awaiting_payment: 'yack-tag--tertiary',
			partially_paid: 'yack-tag--tertiary'
		},
		shipping: {
			shipped: 'yack-tag--secondary',
			partially_shipped: 'yack-tag--tertiary'
		}
	};
</script>

<span
	class="yack-tag {CLASSES[kind][status.state] ?? 'yack-tag--muted'}"
	data-test-yack-payment-state={kind === 'payment' ? status.state : undefined}
	data-test-yack-shipping-state={kind === 'shipping' ? status.state : undefined}>{status.label}</span
>
