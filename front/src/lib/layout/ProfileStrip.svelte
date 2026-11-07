<script lang="ts">
	// Profile strip under the header (demo "Vous naviguez en…"): what the current profile allows.
	// Every right comes from /me (getViewer), the front decides nothing. The "Documentation" right
	// is added once /me exposes canSeeDocumentation (L3-01b).
	import type { Viewer } from '#lib/types.js';

	let { viewer }: { viewer: Viewer } = $props();

	const SUMMARY = {
		visitor: 'Prix et commande en ligne réservés aux comptes e-commerce.',
		pro: 'Pour voir les prix et commander en ligne, demandez l’accès e-commerce.',
		ecom: 'Vos prix HT et la commande en ligne.'
	} as const;

	const rights = $derived([
		{ key: 'prices', label: 'Prix', allowed: viewer.canSeePrices },
		{ key: 'order', label: 'Panier et commande', allowed: viewer.canOrder }
	]);

	const pending = $derived(viewer.orderAccessRequest?.status === 'pending');
</script>

<div class="yack-profile-strip" data-test-yack-profile={viewer.profile}>
	<div class="container yack-profile-strip__in">
		<p class="yack-profile-strip__who">
			<strong>Vous naviguez en {viewer.profileLabel}</strong>
			<span>{SUMMARY[viewer.profile]}</span>
		</p>
		<ul class="yack-profile-strip__rights" aria-label="Ce que permet votre profil">
			{#each rights as right (right.key)}
				<li class="yack-tag {right.allowed ? 'yack-tag--secondary' : 'yack-tag--muted yack-profile-strip__denied'}" data-test-profile-right={right.key} data-allowed={right.allowed}>
					<span aria-hidden="true">{right.allowed ? '✓' : '✕'}</span>
					{right.label}
					<span class="visually-hidden">{right.allowed ? ' : inclus' : ' : non inclus'}</span>
				</li>
			{/each}
		</ul>
		{#if !viewer.authenticated}
			<a class="yack-link yack-profile-strip__action" href="/inscription">Créer mon compte pro</a>
		{:else if pending}
			<span class="yack-profile-strip__action" data-test-order-access-pending>Demande d’ouverture en cours</span>
		{:else if viewer.canRequestOrderAccess}
			<a class="yack-link yack-profile-strip__action" href="/compte/acces-commande">Demander l’accès e-commerce</a>
		{/if}
	</div>
</div>
