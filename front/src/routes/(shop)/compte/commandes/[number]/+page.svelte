<script lang="ts">
	// Order detail (Sylius account order show + Yack hooks: payment method / escompte, payment terms, tracking).
	import AddressBlock from '#lib/account/AddressBlock.svelte';
	import OrdersLocked from '#lib/account/OrdersLocked.svelte';
	import { formatDate, formatDateTime, formatPrice } from '#lib/format.js';
	import OrderStatus from '#lib/order/OrderStatus.svelte';
	import OrderTotals from '#lib/order/OrderTotals.svelte';
	import PaymentInstruction from '#lib/order/PaymentInstruction.svelte';
	import { orderLines } from '#lib/order/types.js';

	let { data } = $props();
	const order = $derived(data.order);
	const lines = $derived(order ? orderLines(order) : []);
</script>

<svelte:head>
	<title>Commande n° {order?.number ?? ''} | Clik&amp;Yack</title>
	<meta name="robots" content="noindex" />
</svelte:head>

{#if !order}
	<div class="mb-4"><h1>Commande</h1></div>
	<OrdersLocked viewer={data.viewer} />
{:else}
	{@const yack = order.yack}
	<div class="mb-4 d-flex flex-wrap align-items-start justify-content-between gap-3">
		<div>
			<h1 data-test-order-number>Commande n° {order.number}</h1>
			<div class="text-body-secondary">Passée le {order.checkoutCompletedAt ? formatDateTime(order.checkoutCompletedAt) : ''}</div>
		</div>
		<a href="/compte/commandes" class="btn btn-sm btn-outline-gray">‹ Toutes mes commandes</a>
	</div>

	<div class="mb-4">
		<div class="row mb-1">
			<div class="col-12 col-sm-4">Paiement</div>
			<div class="col" data-test-order-payment-method>
				{yack.paymentMethod?.name ?? '—'}
				{#if yack.paymentMethod?.escompteLabel}<span class="badge text-bg-success ms-1">{yack.paymentMethod.escompteLabel}</span>{/if}
				<OrderStatus kind="payment" status={yack.paymentStatus} />
			</div>
		</div>
		<div class="row">
			<div class="col-12 col-sm-4">Livraison</div>
			<div class="col"><OrderStatus kind="shipping" status={yack.shippingStatus} /></div>
		</div>
	</div>

	{#if yack.paymentInstruction}
		<div class="mb-4">
			<PaymentInstruction instruction={yack.paymentInstruction} methodName={yack.paymentMethod?.name ?? null} />
		</div>
	{/if}

	<div class="row g-3 mb-4">
		<div class="col-md-6">
			<div class="card border-0 bg-body-tertiary h-100">
				<div class="card-body">
					<div class="fw-bold mb-2">Adresse de facturation</div>
					{#if order.billingAddress}<AddressBlock address={order.billingAddress} />{/if}
				</div>
			</div>
		</div>
		<div class="col-md-6">
			<div class="card border-0 bg-body-tertiary h-100">
				<div class="card-body">
					<div class="fw-bold mb-2">Adresse de livraison</div>
					{#if order.shippingAddress}<AddressBlock address={order.shippingAddress} />{/if}
				</div>
			</div>
		</div>
	</div>

	{#if yack.shipments.length > 0}
		<section class="mb-4" data-test-order-shipments>
			<h2 class="h5">Expédition</h2>
			{#each yack.shipments as shipment (shipment.id)}
				<div class="border rounded p-3 mb-2">
					<div>{shipment.method?.name ?? 'Livraison'}</div>
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

	<section class="mb-4">
		<h2 class="h5">Articles</h2>
		<div class="table-responsive">
			<table class="table align-middle" data-test-order-items>
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
								<div class="d-flex align-items-center gap-3">
									{#if line.image}<img src={line.image} alt="" width="64" height="64" class="rounded border object-fit-contain bg-white" />{/if}
									<div>
										{#if line.url}<a href={line.url} class="link-reset fw-semibold">{line.name}</a>{:else}<span class="fw-semibold">{line.name}</span>{/if}
										<div class="small text-body-secondary">{[line.code, line.typeLabel].filter(Boolean).join(' · ')}</div>
									</div>
								</div>
							</td>
							<td class="text-end">{formatPrice(line.unitPrice)}</td>
							<td class="text-center">{line.quantity}</td>
							<td class="text-end">{formatPrice(line.subtotal)}</td>
						</tr>
					{/each}
				</tbody>
			</table>
		</div>
	</section>

	<div class="row justify-content-end">
		<div class="col-md-7 col-lg-6">
			<div class="card">
				<div class="card-body">
					<OrderTotals totals={yack.totals} escompte={yack.escompte} shippingLabel={yack.shipments[0]?.method ? `${yack.shipments[0].method.name} (HT)` : 'Livraison (HT)'} />
				</div>
			</div>
		</div>
	</div>
{/if}
