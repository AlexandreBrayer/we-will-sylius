<script lang="ts">
	// Order history (Sylius grid sylius_shop_account_order customised in config/packages/yack.yaml).
	import { page } from '$app/state';
	import OrdersLocked from '#lib/account/OrdersLocked.svelte';
	import { formatDate, formatPrice } from '#lib/format.js';
	import OrderStatus from '#lib/order/OrderStatus.svelte';
	import { getOrders } from '#lib/remote/account.remote.js';
	import { getViewer } from '#lib/remote/session.remote.js';

	const history = $derived(await getOrders(page.url.searchParams.get('page') ?? undefined));
</script>

<svelte:head>
	<title>Historique des commandes | Clik&amp;Yack</title>
	<meta name="robots" content="noindex" />
</svelte:head>

<div class="mb-4">
	<h1>Historique des commandes</h1>
	Suivi de vos commandes : paiement, préparation et expédition
</div>

{#if history === null}
	<OrdersLocked viewer={await getViewer()} />
{:else if history.orders.length === 0}
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
				{#each history.orders as order (order.tokenValue)}
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

	{#if history.pages > 1}
		<nav aria-label="Pagination">
			<ul class="pagination">
				{#each Array.from({ length: history.pages }, (_, i) => i + 1) as number (number)}
					<li class="page-item" class:active={number === history.page}>
						<a class="page-link" href="?page={number}" aria-current={number === history.page ? 'page' : undefined}>{number}</a>
					</li>
				{/each}
			</ul>
		</nav>
	{/if}
{/if}
