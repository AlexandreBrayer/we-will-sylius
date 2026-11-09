<script lang="ts">
	// Shown instead of prices to a visitor (log in / create an account) or a PRO (order access
	// request: open or pending), with the texts of the product card. `framed`: in its own card (SAV
	// exploded view); the product page purchase box is already one.
	import { page } from '$app/state';
	import { formatDate } from '#lib/format.js';
	import type { Viewer } from '#lib/types.js';

	let { viewer, framed = true }: { viewer: Viewer; framed?: boolean } = $props();
	const request = $derived(viewer.orderAccessRequest);
</script>

{#snippet notice()}
	{#if viewer.canRequestOrderAccess}
		{#if request?.status === 'pending'}
			<p class="yack-buybox__lock" data-test-yack-pricing-notice="pro" data-test-yack-order-access-cta="pending">Demande d'ouverture en cours</p>
			<p class="yack-buybox__text">Envoyée le {formatDate(request.createdAt)} : les prix et le panier apparaîtront dès sa validation par Yack.</p>
			<div class="yack-buybox__actions">
				<a class="btn btn-secondary" href="/compte/acces-commande">Suivre ma demande</a>
			</div>
		{:else}
			<p class="yack-buybox__lock" data-test-yack-pricing-notice="pro" data-test-yack-order-access-cta="open">Prix et commande réservés aux comptes e-commerce</p>
			<p class="yack-buybox__text">Votre compte pro donne accès à la documentation. Demandez l'accès e-commerce pour voir les prix et commander en ligne.</p>
			<div class="yack-buybox__actions">
				<a class="btn btn-secondary" href="/compte/acces-commande">Accès e-commerce</a>
			</div>
		{/if}
	{:else}
		<p class="yack-buybox__lock" data-test-yack-pricing-notice={viewer.profile}>Prix réservés aux comptes e-commerce</p>
		<p class="yack-buybox__text">Connectez-vous ou créez votre compte pro pour accéder aux prix et à la commande en ligne.</p>
		<div class="yack-buybox__actions">
			<a class="btn btn-secondary" href="/connexion?redirect={encodeURIComponent(page.url.pathname + page.url.search)}">Se connecter</a>
			<a class="btn btn-light" href="/inscription">Créer un compte</a>
		</div>
	{/if}
{/snippet}

{#if framed}
	<div class="yack-buybox my-4" role="note">{@render notice()}</div>
{:else}
	{@render notice()}
{/if}
