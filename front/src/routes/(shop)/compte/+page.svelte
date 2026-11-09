<script lang="ts">
	// Account dashboard: Sylius dashboard + Yack block (templates/shop/yack/account/dashboard/company.html.twig).
	import { formatDate, formatPrice } from '#lib/format.js';
	import Icon from '#lib/layout/Icon.svelte';
	import OrderStatus from '#lib/order/OrderStatus.svelte';
	import { getCustomer, getRecentOrders } from '#lib/remote/account.remote.js';
	import { getViewer } from '#lib/remote/session.remote.js';

	const [viewer, customer, recent] = $derived(await Promise.all([getViewer(), getCustomer(), getRecentOrders()]));
	const accessRequest = $derived(viewer.orderAccessRequest);
	const address = $derived(viewer.billingAddress);
</script>

<svelte:head>
	<title>Mon compte | Clik&amp;Yack</title>
	<meta name="robots" content="noindex" />
</svelte:head>

<div class="yack-page-head d-flex flex-wrap align-items-end justify-content-between gap-3">
	<div>
		<h1>Mon compte</h1>
		<p>Gérer vos informations personnelles et préférences</p>
	</div>
	<div class="d-flex flex-wrap gap-2">
		<a href="/compte/profil" class="btn btn-sm btn-ghost">Modifier</a>
		<a href="/compte/profil#mot-de-passe" class="btn btn-sm btn-ghost">Changer votre mot de passe</a>
	</div>
</div>

<section class="yack-account-card" data-test-yack-account-dashboard={viewer.profile}>
	<div class="yack-account-card__tags">
		<span class="yack-tag yack-tag--primary" data-test-account-profile>{viewer.profileLabel}</span>
		{#if viewer.canOrder}
			<span class="yack-tag yack-tag--secondary">Commande en ligne ouverte</span>
		{/if}
	</div>

	<dl class="yack-facts">
		<dt>Contact</dt>
		<dd><span data-test-account-full-name>{customer.fullName}</span><br /><span class="fw-normal">{customer.email}</span></dd>
		{#if viewer.company}
			<dt>Société</dt>
			<dd data-test-yack-account-company>{viewer.company}</dd>
		{/if}
		{#if viewer.siret}
			<dt>SIRET</dt>
			<dd>{viewer.siret}</dd>
		{/if}
		{#if viewer.vatNumber}
			<dt>N° TVA intracom.</dt>
			<dd>{viewer.vatNumber}</dd>
		{/if}
		{#if viewer.phoneNumber}
			<dt>Téléphone</dt>
			<dd>{viewer.phoneNumber}</dd>
		{/if}
		{#if address}
			<dt>Adresse de facturation</dt>
			<dd>{address.street}, {address.postcode} {address.city}</dd>
		{/if}
	</dl>

	{#if viewer.canRequestOrderAccess}
		{#if accessRequest?.status === 'pending'}
			<div class="yack-callout yack-callout--pending mt-4" data-test-yack-order-access-cta="pending">
				<span class="yack-callout__title">Demande d'ouverture à la commande en cours</span>
				Envoyée le {formatDate(accessRequest.createdAt)}.
				<a class="yack-link d-inline-flex" href="/compte/acces-commande">Suivre ma demande <Icon name="chevron-right" /></a>
			</div>
		{:else}
			<div class="yack-callout mt-4 d-flex flex-wrap align-items-center justify-content-between gap-3" data-test-yack-order-access-cta="open">
				<div>
					<span class="yack-callout__title">Commander en ligne</span>
					Prix, disponibilités et commande directe : demandez l'ouverture de votre compte.
				</div>
				<a class="btn btn-primary" href="/compte/acces-commande">Demander l'ouverture à la commande</a>
			</div>
		{/if}
	{/if}
</section>

{#if recent}
	<section class="yack-account-card" data-test-account-recent-orders>
		<div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2 mb-3">
			<h2 class="yack-account-card__title mb-0">Dernières commandes</h2>
			{#if recent.total > 0}<a href="/compte/commandes" class="yack-link">Toutes mes commandes ({recent.total}) <Icon name="chevron-right" /></a>{/if}
		</div>
		{#if recent.orders.length === 0}
			<p class="text-body-secondary mb-0">Aucune commande pour le moment. <a href="/produits">Découvrir le catalogue</a></p>
		{:else}
			<div class="table-responsive">
				<table class="table yack-lines align-middle">
					<thead>
						<tr><th>Commande</th><th>Date</th><th>Paiement</th><th>Livraison</th><th class="text-end">Total TTC</th></tr>
					</thead>
					<tbody>
						{#each recent.orders as order (order.tokenValue)}
							<tr>
								<td class="text-nowrap"><a class="yack-line__name" href="/compte/commandes/{order.number}">n° {order.number}</a></td>
								<td>{order.checkoutCompletedAt ? formatDate(order.checkoutCompletedAt) : ''}</td>
								<td><OrderStatus kind="payment" status={order.yack.paymentStatus} /></td>
								<td><OrderStatus kind="shipping" status={order.yack.shippingStatus} /></td>
								<td class="text-end text-nowrap fw-bold">{formatPrice(order.yack.totals.total)}</td>
							</tr>
						{/each}
					</tbody>
				</table>
			</div>
		{/if}
	</section>
{/if}

<div class="row g-3 mb-4">
	<div class="col-sm-4">
		<a class="yack-account-shortcut" href="/compte/commandes">
			<span class="yack-account-shortcut__icon"><Icon name="truck" /></span>
			<span><strong>Mes commandes</strong><small>Suivi, paiement, livraison</small></span>
		</a>
	</div>
	<div class="col-sm-4">
		<a class="yack-account-shortcut" href="/compte/adresses">
			<span class="yack-account-shortcut__icon"><Icon name="pin" /></span>
			<span><strong>Mes adresses</strong><small>Facturation et livraison</small></span>
		</a>
	</div>
	<div class="col-sm-4">
		<a class="yack-account-shortcut" href="/sav">
			<span class="yack-account-shortcut__icon"><Icon name="part" /></span>
			<span><strong>SAV et pièces</strong><small>Recherche par n° de série</small></span>
		</a>
	</div>
</div>
