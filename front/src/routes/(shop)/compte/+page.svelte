<script lang="ts">
	// Account dashboard: Sylius dashboard + Yack block (templates/shop/yack/account/dashboard/company.html.twig).
	import { formatDate, formatPrice } from '#lib/format.js';
	import OrderStatus from '#lib/order/OrderStatus.svelte';

	let { data } = $props();
	const viewer = $derived(data.viewer);
	const accessRequest = $derived(viewer.orderAccessRequest);
	const address = $derived(viewer.billingAddress);
</script>

<svelte:head>
	<title>Mon compte | Clik&amp;Yack</title>
	<meta name="robots" content="noindex" />
</svelte:head>

<div class="mb-4">
	<h1>Mon compte</h1>
	Gérer vos informations personnelles et préférences
</div>

<div class="row mb-3">
	<div class="col-12 col-sm mb-3 mb-sm-0">
		<strong data-test-account-full-name>{data.customer.fullName}</strong>
		<div>{data.customer.email}</div>
	</div>
	<div class="col-12 col-sm-auto d-flex flex-wrap gap-2 align-items-start">
		<a href="/compte/profil" class="btn btn-sm btn-icon btn-outline-gray">Modifier</a>
		<a href="/compte/profil#mot-de-passe" class="btn btn-sm btn-icon btn-outline-gray">Changer votre mot de passe</a>
	</div>
</div>

<div class="card yack-account-card my-4" data-test-yack-account-dashboard={viewer.profile}>
	<div class="card-body p-4">
		<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
			<span class="badge bg-yack" data-test-account-profile>{viewer.profileLabel}</span>
			{#if viewer.canOrder}
				<span class="badge text-bg-success">Commande en ligne ouverte</span>
			{/if}
		</div>

		<dl class="row mb-0">
			{#if viewer.company}
				<dt class="col-sm-4 text-body-secondary fw-normal">Société</dt>
				<dd class="col-sm-8 fw-semibold" data-test-yack-account-company>{viewer.company}</dd>
			{/if}
			{#if viewer.siret}
				<dt class="col-sm-4 text-body-secondary fw-normal">SIRET</dt>
				<dd class="col-sm-8">{viewer.siret}</dd>
			{/if}
			{#if viewer.vatNumber}
				<dt class="col-sm-4 text-body-secondary fw-normal">N° TVA intracom.</dt>
				<dd class="col-sm-8">{viewer.vatNumber}</dd>
			{/if}
			{#if viewer.phoneNumber}
				<dt class="col-sm-4 text-body-secondary fw-normal">Téléphone</dt>
				<dd class="col-sm-8">{viewer.phoneNumber}</dd>
			{/if}
			{#if address}
				<dt class="col-sm-4 text-body-secondary fw-normal">Adresse de facturation</dt>
				<dd class="col-sm-8">{address.street}, {address.postcode} {address.city}</dd>
			{/if}
		</dl>

		{#if viewer.canRequestOrderAccess}
			<hr />
			{#if accessRequest?.status === 'pending'}
				<div class="alert alert-warning mb-0" data-test-yack-order-access-cta="pending">
					<strong>Demande d'ouverture à la commande en cours</strong> — envoyée le {formatDate(accessRequest.createdAt)}.
					<a href="/compte/acces-commande">Suivre ma demande</a>
				</div>
			{:else}
				<div class="d-flex flex-wrap align-items-center justify-content-between gap-3" data-test-yack-order-access-cta="open">
					<div>
						<div class="fw-bold">Commander en ligne</div>
						<div class="text-body-secondary">Prix, disponibilités et commande directe : demandez l'ouverture de votre compte.</div>
					</div>
					<a class="btn btn-primary" href="/compte/acces-commande">Demander l'ouverture à la commande</a>
				</div>
			{/if}
		{/if}
	</div>
</div>

{#if data.recentOrders}
	<section class="mb-4" data-test-account-recent-orders>
		<div class="d-flex align-items-baseline justify-content-between mb-2">
			<h2 class="h5 mb-0">Dernières commandes</h2>
			{#if data.orderCount > 0}<a href="/compte/commandes" class="small">Toutes mes commandes ({data.orderCount})</a>{/if}
		</div>
		{#if data.recentOrders.length === 0}
			<p class="text-body-secondary">Aucune commande pour le moment. <a href="/produits">Découvrir le catalogue</a></p>
		{:else}
			<div class="table-responsive">
				<table class="table align-middle mb-0">
					<thead>
						<tr><th>Commande</th><th>Date</th><th>Paiement</th><th>Livraison</th><th class="text-end">Total TTC</th></tr>
					</thead>
					<tbody>
						{#each data.recentOrders as order (order.tokenValue)}
							<tr>
								<td><a href="/compte/commandes/{order.number}">n° {order.number}</a></td>
								<td>{order.checkoutCompletedAt ? formatDate(order.checkoutCompletedAt) : ''}</td>
								<td><OrderStatus kind="payment" status={order.yack.paymentStatus} /></td>
								<td><OrderStatus kind="shipping" status={order.yack.shippingStatus} /></td>
								<td class="text-end">{formatPrice(order.yack.totals.total)}</td>
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
		<a class="card h-100 text-decoration-none yack-account-shortcut" href="/compte/commandes">
			<div class="card-body"><div class="fw-bold">Mes commandes</div><div class="small text-body-secondary">Suivi, paiement, livraison</div></div>
		</a>
	</div>
	<div class="col-sm-4">
		<a class="card h-100 text-decoration-none yack-account-shortcut" href="/compte/adresses">
			<div class="card-body"><div class="fw-bold">Mes adresses</div><div class="small text-body-secondary">Facturation et livraison</div></div>
		</a>
	</div>
	<div class="col-sm-4">
		<a class="card h-100 text-decoration-none yack-account-shortcut" href="/sav">
			<div class="card-body"><div class="fw-bold">SAV &amp; pièces</div><div class="small text-body-secondary">Recherche par n° de série</div></div>
		</a>
	</div>
</div>
