<script lang="ts">
	// Tabs of the builder lists (Pages | Templates), first row of the content column (.yb-wrap), above the cards.
	let { current }: { current: 'pages' | 'templates' } = $props();
</script>

<nav aria-label="Builder">
	<a href="/cms-admin/builder" aria-current={current === 'pages' ? 'page' : undefined}>Pages</a>
	<a href="/cms-admin/builder/templates" aria-current={current === 'templates' ? 'page' : undefined}>Templates</a>
</nav>

<style>
	/* Onglets en pilule, au bord gauche des cartes. */
	nav {
		display: flex;
		gap: 0.5rem;
		justify-self: start;
	}
	a {
		color: var(--yack-black);
		text-decoration: none;
		padding: 0.4375rem 1.25rem;
		border-radius: var(--yack-radius-pill);
		font-size: 0.875rem;
		font-weight: 700;
		line-height: 1.5rem;
	}
	a:hover {
		background: var(--yack-magenta-10);
		color: var(--yack-magenta);
	}
	a[aria-current='page'] {
		background: var(--yack-magenta);
		color: var(--yack-white);
	}
	a:focus-visible {
		outline: 2px solid var(--yack-magenta);
		outline-offset: 2px;
	}
</style>
