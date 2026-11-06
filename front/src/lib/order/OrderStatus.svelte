<script lang="ts">
	// Payment / shipping badge of an order (port of templates/shop/yack/order/_status.html.twig);
	// the French label comes from the API (yack.paymentStatus / yack.shippingStatus).
	import type { OrderState } from './types.js';

	let { kind, status }: { kind: 'payment' | 'shipping'; status: OrderState } = $props();

	const CLASSES: Record<'payment' | 'shipping', Record<string, string>> = {
		payment: {
			paid: 'text-bg-success',
			awaiting_payment: 'text-bg-warning',
			partially_paid: 'text-bg-warning',
			refunded: 'text-bg-secondary',
			partially_refunded: 'text-bg-secondary',
			cancelled: 'text-bg-secondary'
		},
		shipping: {
			shipped: 'text-bg-success',
			partially_shipped: 'text-bg-info',
			ready: 'text-bg-light',
			cancelled: 'text-bg-secondary'
		}
	};
</script>

<span
	class="badge {CLASSES[kind][status.state] ?? 'text-bg-light'}"
	data-test-yack-payment-state={kind === 'payment' ? status.state : undefined}
	data-test-yack-shipping-state={kind === 'shipping' ? status.state : undefined}>{status.label}</span
>
