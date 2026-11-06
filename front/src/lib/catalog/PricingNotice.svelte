<script lang="ts">
	// Port of templates/shop/yack/account/product_pricing_notice.html.twig: shown instead of prices
	// to a visitor (log in / register) or a PRO (order access request: open or pending).
	import { page } from '$app/state';
	import type { Viewer } from '#lib/types.js';

	let { viewer }: { viewer: Viewer } = $props();
</script>

{#if viewer.canRequestOrderAccess}
	{#if viewer.orderAccessRequest?.status === 'pending'}
		<div class="alert alert-warning my-4" role="note" data-test-yack-pricing-notice="pro" data-test-yack-order-access-cta="pending">
			<div class="fw-bold">Demande d'ouverture à la commande en cours</div>
			<div>
				Envoyée le {new Date(viewer.orderAccessRequest.createdAt).toLocaleDateString('fr-FR')} : les prix et le panier apparaîtront dès sa validation par Yack.
				<a href="/compte/acces-commande">Suivre ma demande</a>
			</div>
		</div>
	{:else}
		<div class="card border-primary my-4" data-test-yack-pricing-notice="pro" data-test-yack-order-access-cta="open">
			<div class="card-body">
				<div class="fw-bold mb-1">Commander en ligne</div>
				<p class="mb-3">Demandez l'ouverture de votre compte à la commande : prix, disponibilités et commande directe sur Clik&amp;Yack.</p>
				<a class="btn btn-primary" href="/compte/acces-commande">Demander l'ouverture à la commande</a>
			</div>
		</div>
	{/if}
{:else}
	<div class="alert alert-info my-4" role="note" data-test-yack-pricing-notice={viewer.profile}>
		<div class="fw-bold">Prix réservés aux clients connectés</div>
		<div>
			<a href="/connexion?redirect={encodeURIComponent(page.url.pathname)}">Connectez-vous</a> ou
			<a href="/inscription">créez un compte</a> pour consulter les prix et commander en ligne.
		</div>
	</div>
{/if}
