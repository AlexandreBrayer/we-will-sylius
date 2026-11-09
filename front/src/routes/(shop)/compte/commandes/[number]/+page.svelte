<script lang="ts">
	// Order detail (Sylius account order show + Yack hooks: payment method / escompte, payment terms,
	// payment history with the card attempts, link to pay an order awaiting its payment, tracking).
	import AddressBlock from '#lib/account/AddressBlock.svelte';
	import OrdersLocked from '#lib/account/OrdersLocked.svelte';
	import { formatDate, formatDateTime, formatPrice } from '#lib/format.js';
	import OrderStatus from '#lib/order/OrderStatus.svelte';
	import OrderTotals from '#lib/order/OrderTotals.svelte';
	import PaymentHistory from '#lib/order/PaymentHistory.svelte';
	import PaymentInstruction from '#lib/order/PaymentInstruction.svelte';
	import { orderLines } from '#lib/order/types.js';
	import { getOrder } from '#lib/remote/account.remote.js';
	import { getViewer } from '#lib/remote/session.remote.js';

	let { params } = $props();
	const order = $derived(await getOrder(params.number));
	const lines = $derived(order ? orderLines(order) : []);
</script>

<svelte:head>
	<title>Commande n° {order?.number ?? ''} | Clik&amp;Yack</title>
	<meta name="robots" content="noindex" />
</svelte:head>

{#if !order}
	<div class="yack-page-head"><h1>Commande</h1></div>
	<OrdersLocked viewer={await getViewer()} />
{:else}
	{@const yack = order.yack}
	<div class="yack-page-head d-flex flex-wrap align-items-end justify-content-between gap-3">
		<div>
			<h1 data-test-order-number>Commande n° {order.number}</h1>
			<p>Passée le {order.checkoutCompletedAt ? formatDateTime(order.checkoutCompletedAt) : ''}</p>
		</div>
		<a href="/compte/commandes" class="btn btn-sm btn-ghost">‹ Toutes mes commandes</a>
	</div>

	<section class="yack-account-card">
		<dl class="yack-facts">
			<dt>Paiement</dt>
			<dd class="d-flex flex-wrap gap-2 align-items-center" data-test-order-payment-method>
				{yack.paymentMethod?.name ?? 'Non choisi'}
				{#if yack.paymentMethod?.escompteLabel}<span class="yack-tag yack-tag--secondary">{yack.paymentMethod.escompteLabel}</span>{/if}
				<OrderStatus kind="payment" status={yack.paymentStatus} />
			</dd>
			<dt>Livraison</dt>
			<dd><OrderStatus kind="shipping" status={yack.shippingStatus} /></dd>
		</dl>

		{#if yack.paymentInstruction}
			<div class="mt-4">
				<PaymentInstruction
					instruction={yack.paymentInstruction}
					methodName={yack.paymentMethod?.name ?? null}
					payUrl={yack.payable ? `/commande/reglement/${encodeURIComponent(order.tokenValue)}` : null}
				/>
			</div>
		{/if}
	</section>

	{#if yack.payments.some((payment) => payment.card) || yack.payments.length > 1}
		<section class="yack-account-card" data-test-order-payments>
			<h2 class="yack-account-card__title">Historique des paiements</h2>
			<PaymentHistory payments={yack.payments} />
		</section>
	{/if}

	<div class="row g-3 mb-4">
		<div class="col-md-6">
			<div class="yack-recap-card">
				<div class="yack-recap-card__head"><h2>Adresse de facturation</h2></div>
				{#if order.billingAddress}<AddressBlock address={order.billingAddress} />{/if}
			</div>
		</div>
		<div class="col-md-6">
			<div class="yack-recap-card">
				<div class="yack-recap-card__head"><h2>Adresse de livraison</h2></div>
				{#if order.shippingAddress}<AddressBlock address={order.shippingAddress} />{/if}
			</div>
		</div>
	</div>

	{#if yack.shipments.length > 0}
		<section class="yack-account-card" data-test-order-shipments>
			<h2 class="yack-account-card__title">Expédition</h2>
			{#each yack.shipments as shipment (shipment.id)}
				<div class="d-flex flex-wrap gap-3 align-items-center justify-content-between py-2">
					<div class="fw-bold">{shipment.method?.name ?? 'Livraison'}</div>
					{#if shipment.tracking}
						<div class="small" data-test-shipment-tracking>
							N° de suivi : <strong>{shipment.tracking}</strong>
							{#if shipment.shippedAt}<span class="text-body-secondary">· expédiée le {formatDate(shipment.shippedAt)}</span>{/if}
						</div>
					{:else}
						<div class="small text-body-secondary">N° de suivi communiqué à l'expédition.</div>
					{/if}
				</div>
			{/each}
		</section>
	{/if}

	<section class="yack-account-card">
		<h2 class="yack-account-card__title">Articles</h2>
		<div class="table-responsive">
			<table class="table yack-lines align-middle" data-test-order-items>
				<thead>
					<tr>
						<th>Article</th>
						<th class="text-end">Prix unitaire HT</th>
						<th class="text-center">Quantité</th>
						<th class="text-end">Total HT</th>
					</tr>
				</thead>
				<tbody>
					{#each lines as line (line.id)}
						<tr data-test-order-item={line.code}>
							<td>
								<div class="yack-line">
									<span class="yack-thumb yack-thumb--sm">{#if line.image}<img src={line.image} alt="" width="64" height="64" />{/if}</span>
									<div>
										{#if line.url}<a href={line.url} class="yack-line__name">{line.name}</a>{:else}<span class="yack-line__name">{line.name}</span>{/if}
										<div class="yack-line__meta">{[line.code, line.typeLabel].filter(Boolean).join(' · ')}</div>
									</div>
								</div>
							</td>
							<td class="text-end text-nowrap">{formatPrice(line.unitPrice)}</td>
							<td class="text-center">{line.quantity}</td>
							<td class="text-end text-nowrap fw-bold">{formatPrice(line.subtotal)}</td>
						</tr>
					{/each}
				</tbody>
			</table>
		</div>
	</section>

	<div class="row justify-content-end">
		<div class="col-md-8 col-lg-6">
			<div class="yack-summary">
				<OrderTotals totals={yack.totals} escompte={yack.escompte} shippingLabel={yack.shipments[0]?.method ? `${yack.shipments[0].method.name} (HT)` : 'Livraison (HT)'} />
			</div>
		</div>
	</div>
{/if}
