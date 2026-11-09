<script lang="ts">
	// Payments of a placed order, oldest first (Sylius payments): each card attempt with its outcome
	// (refused, abandoned, paid), masked card, 3-D Secure and transaction reference.
	import { formatDateTime, formatPrice } from '#lib/format.js';
	import type { OrderPaymentHistory } from './types.js';

	let { payments }: { payments: OrderPaymentHistory[] } = $props();
</script>

<ul class="list-unstyled mb-0" data-test-payment-history>
	{#each payments as payment (payment.id)}
		<li class="d-flex flex-wrap gap-2 justify-content-between py-2 border-bottom" data-test-payment-history-item={payment.state}>
			<div>
				<div class="fw-bold">
					{payment.method ?? 'Paiement'}
					{#if payment.card?.card}<span class="fw-normal">· {payment.card.card.label}</span>{/if}
				</div>
				<div class="small text-body-secondary">
					{payment.card ? payment.card.message : payment.stateLabel}
					{#if payment.card?.threeDSecure} · 3-D Secure{/if}
					{#if payment.card?.oneClick} · carte enregistrée{/if}
					{#if payment.card?.transactionReference} · réf. {payment.card.transactionReference}{/if}
				</div>
			</div>
			<div class="text-end small">
				<span class="yack-tag {payment.state === 'completed' ? 'yack-tag--secondary' : 'yack-tag--muted'}">{payment.stateLabel}</span>
				<div class="text-body-secondary mt-1">
					{formatPrice(payment.amount)}
					{#if payment.card?.concludedAt ?? payment.updatedAt}· {formatDateTime((payment.card?.concludedAt ?? payment.updatedAt)!)}{/if}
				</div>
			</div>
		</li>
	{/each}
</ul>
