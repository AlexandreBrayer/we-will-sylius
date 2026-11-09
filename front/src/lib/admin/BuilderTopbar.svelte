<script lang="ts">
	// Header of the builder lists: logo, Pages | Templates, then the shop (new tab) and back to the Sylius admin.
	let { current }: { current: 'pages' | 'templates' } = $props();
</script>

<header class="yb-topbar">
	<img class="yb-logo" src="/yack/logo-yack.png" alt="Yack" />
	<strong>Builder</strong>
	<nav aria-label="Builder">
		<a href="/cms-admin/builder" aria-current={current === 'pages' ? 'page' : undefined}>Pages</a>
		<a href="/cms-admin/builder/templates" aria-current={current === 'templates' ? 'page' : undefined}>Templates</a>
	</nav>
	<div class="exits">
		<a class="yb-btn yb-btn-outline" href="/" target="_blank" rel="noopener">Voir le site</a>
		<a class="yb-btn yb-btn-outline" href={current === 'pages' ? '/admin/yack/contenu/pages' : '/admin/yack/contenu/templates'}>Retour à l'admin Sylius</a>
	</div>
</header>

<style>
	/* Barre du haut du back-office (.yb-topbar, admin.css) : logo, « Builder », onglets en pilule, puis les deux sorties, un peu à l'écart. */
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
	.exits {
		display: flex;
		gap: 0.5rem;
		margin-left: 2rem;
	}
</style>
