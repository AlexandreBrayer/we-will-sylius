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
	.topbar {
		display: flex;
		align-items: center;
		gap: 1rem;
		padding: 0.6rem 1.5rem;
		background: var(--yack-dark);
		color: #fff;
	}
	.topbar .yb-logo {
		background: #fff;
		border-radius: 0.25rem;
		padding: 2px;
	}
	nav {
		display: flex;
		gap: 0.25rem;
		margin-left: 1rem;
	}
	nav a {
		color: #fff;
		text-decoration: none;
		padding: 0.35rem 0.8rem;
		border-radius: 0.375rem;
		font-size: 0.9rem;
		opacity: 0.8;
	}
	nav a:hover {
		opacity: 1;
	}
	nav a[aria-current='page'] {
		background: var(--yack-magenta);
		opacity: 1;
	}
	.spacer {
		flex: 1;
	}
</style>
