<script lang="ts">
	// Order history (Sylius grid sylius_shop_account_order customised in config/packages/yack.yaml).
	import { page } from '$app/state';
	import OrdersLocked from '#lib/account/OrdersLocked.svelte';
	import { pageWindow } from '#lib/catalog/pagination.js';
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

<div class="yack-page-head">
	<h1>Historique des commandes</h1>
	<p>Suivi de vos commandes : paiement, préparation et expédition</p>
</div>

{#if history === null}
	<OrdersLocked viewer={await getViewer()} />
{:else if history.orders.length === 0}
	<div class="yack-account-card text-center" data-test-orders-empty>
		<p class="mb-3">Vous n'avez pas encore passé de commande.</p>
		<a class="btn btn-primary" href="/produits">Découvrir le catalogue</a>
	</div>
{:else}
	<div class="yack-account-card">
		<div class="table-responsive">
			<table class="table yack-lines align-middle" data-test-grid-table>
				<thead>
					<tr>
						<th>Commande</th>
						<th>Date</th>
						<th>Paiement</th>
						<th>Livraison</th>
						<th class="text-end">Total TTC</th>
						<th><span class="visually-hidden">Détail</span></th>
					</tr>
				</thead>
				<tbody>
					{#each history.orders as order (order.tokenValue)}
						<tr data-test-order={order.number}>
							<td class="text-nowrap"><a href="/compte/commandes/{order.number}" class="yack-line__name">n° {order.number}</a></td>
							<td class="text-nowrap">{order.checkoutCompletedAt ? formatDate(order.checkoutCompletedAt) : ''}</td>
							<td>
								<div class="small text-body-secondary mb-1">{order.yack.paymentMethod?.name ?? 'Non choisi'}</div>
								<OrderStatus kind="payment" status={order.yack.paymentStatus} />
							</td>
							<td><OrderStatus kind="shipping" status={order.yack.shippingStatus} /></td>
							<td class="text-end text-nowrap fw-bold">{formatPrice(order.yack.totals.total)}</td>
							<td class="text-end"><a href="/compte/commandes/{order.number}" class="btn btn-sm btn-ghost">Voir</a></td>
						</tr>
					{/each}
				</tbody>
			</table>
		</div>
	</div>

	{#if history.pages > 1}
		<nav aria-label="Pagination">
			<ul class="pagination yack-pagination justify-content-center">
				{#each pageWindow(history.page, history.pages) as number, i (number === 'gap' ? `gap-${i}` : number)}
					{#if number === 'gap'}
						<li class="page-item disabled yack-pagination__gap" aria-hidden="true"><span class="page-link">…</span></li>
					{:else}
						<li class="page-item" class:active={number === history.page}>
							<a class="page-link" href="?page={number}" aria-current={number === history.page ? 'page' : undefined}>{number}</a>
						</li>
					{/if}
				{/each}
			</ul>
		</nav>
	{/if}
{/if}
