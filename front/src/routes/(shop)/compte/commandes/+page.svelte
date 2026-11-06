<script lang="ts">
	// Order history (Sylius grid sylius_shop_account_order customised in config/packages/yack.yaml).
	import OrdersLocked from '#lib/account/OrdersLocked.svelte';
	import { formatDate, formatPrice } from '#lib/format.js';
	import OrderStatus from '#lib/order/OrderStatus.svelte';

	let { data } = $props();
</script>

<svelte:head>
	<title>Historique des commandes | Clik&amp;Yack</title>
	<meta name="robots" content="noindex" />
</svelte:head>

<div class="mb-4">
	<h1>Historique des commandes</h1>
	Suivi de vos commandes : paiement, préparation et expédition
</div>

{#if data.orders === null}
	<OrdersLocked viewer={data.viewer} />
{:else if data.orders.length === 0}
	<p class="text-body-secondary" data-test-orders-empty>Vous n'avez pas encore passé de commande. <a href="/produits">Découvrir le catalogue</a></p>
{:else}
	<div class="table-responsive">
		<table class="table align-middle" data-test-grid-table>
			<thead>
				<tr>
					<th>Commande</th>
					<th>Date</th>
					<th>Paiement</th>
					<th>Livraison</th>
					<th class="text-end">Total TTC</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				{#each data.orders as order (order.tokenValue)}
					<tr data-test-order={order.number}>
						<td><a href="/compte/commandes/{order.number}" class="fw-semibold">n° {order.number}</a></td>
						<td>{order.checkoutCompletedAt ? formatDate(order.checkoutCompletedAt) : ''}</td>
						<td>
							<div class="small">{order.yack.paymentMethod?.name ?? '—'}</div>
							<OrderStatus kind="payment" status={order.yack.paymentStatus} />
						</td>
						<td><OrderStatus kind="shipping" status={order.yack.shippingStatus} /></td>
						<td class="text-end">{formatPrice(order.yack.totals.total)}</td>
						<td class="text-end"><a href="/compte/commandes/{order.number}" class="btn btn-sm btn-outline-gray">Voir</a></td>
					</tr>
				{/each}
			</tbody>
		</table>
	</div>

	{#if data.pages > 1}
		<nav aria-label="Pagination">
			<ul class="pagination">
				{#each Array.from({ length: data.pages }, (_, i) => i + 1) as page (page)}
					<li class="page-item" class:active={page === data.page}>
						<a class="page-link" href="?page={page}" aria-current={page === data.page ? 'page' : undefined}>{page}</a>
					</li>
				{/each}
			</ul>
		</nav>
	{/if}
{/if}
