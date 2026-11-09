<script lang="ts">
	// Header of the builder lists: Pages | Templates, the shop (new tab), back to the Sylius admin.
	let { current }: { current: 'pages' | 'templates' } = $props();
</script>

<header class="topbar">
	<img class="yb-logo" src="/yack/logo-yack.png" alt="Yack" />
	<strong>Builder</strong>
	<nav aria-label="Builder">
		<a href="/cms-admin/builder" aria-current={current === 'pages' ? 'page' : undefined}>Pages</a>
		<a href="/cms-admin/builder/templates" aria-current={current === 'templates' ? 'page' : undefined}>Templates</a>
	</nav>
	<span class="spacer"></span>
	<a class="yb-btn yb-btn-outline" href="/" target="_blank" rel="noopener">Voir le site</a>
	<a class="yb-btn yb-btn-outline" href={current === 'pages' ? '/admin/yack/contenu/pages' : '/admin/yack/contenu/templates'}>Retour à l'admin Sylius</a>
</header>

<style>
	/* Barre du haut blanche, comme celle du back-office : logo en couleur, onglets en pilule, accès à droite. */
	.topbar {
		display: flex;
		align-items: center;
		gap: 1rem;
		padding: 0.75rem 1.5rem;
		background: var(--yack-white);
		color: var(--yack-black);
		border-bottom: 1px solid var(--yack-divider);
	}
	.topbar .yb-logo {
		height: 3rem;
	}
	strong {
		font-size: 1.25rem;
		font-weight: 900;
	}
	nav {
		display: flex;
		gap: 0.5rem;
		margin-left: 1rem;
	}
	nav a {
		color: var(--yack-black);
		text-decoration: none;
		padding: 0.4375rem 1.25rem;
		border-radius: var(--yack-radius-pill);
		font-size: 0.875rem;
		font-weight: 700;
		line-height: 1.5rem;
	}
	nav a:hover {
		background: var(--yack-magenta-10);
		color: var(--yack-magenta);
	}
	nav a[aria-current='page'] {
		background: var(--yack-magenta);
		color: var(--yack-white);
	}
	nav a:focus-visible {
		outline: 2px solid var(--yack-magenta);
		outline-offset: 2px;
	}
	.spacer {
		flex: 1;
	}
</style>
