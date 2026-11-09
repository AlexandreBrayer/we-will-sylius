<script lang="ts">
	// Order pages seen by an account that cannot order (PRO): why, and how to get the access.
	import { formatDate } from '#lib/format.js';
	import type { Viewer } from '#lib/types.js';

	let { viewer }: { viewer: Viewer } = $props();
	const request = $derived(viewer.orderAccessRequest);
</script>

<div class="yack-account-card" data-test-orders-locked>
	<div class="yack-account-card__tags">
		<span class="yack-tag yack-tag--tertiary">{viewer.profileLabel}</span>
	</div>
	<h2 class="h4">La commande en ligne n'est pas encore ouverte sur votre compte</h2>
	<p>
		Votre compte professionnel donne accès au SAV et aux pièces détachées par n° de série. Les prix, le panier et l'historique de commandes
		sont réservés aux comptes ouverts à la commande en ligne.
	</p>
	{#if request?.status === 'pending'}
		<div class="yack-callout yack-callout--pending" data-test-yack-order-access-cta="pending">
			<span class="yack-callout__title">Demande d'ouverture à la commande en cours</span>
			Envoyée le {formatDate(request.createdAt)}. <a href="/compte/acces-commande">Suivre ma demande</a>
		</div>
	{:else if viewer.canRequestOrderAccess}
		<a class="btn btn-primary" href="/compte/acces-commande" data-test-yack-order-access-cta="open">Demander l'ouverture à la commande</a>
	{/if}
</div>
