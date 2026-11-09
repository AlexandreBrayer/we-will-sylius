<script lang="ts">
	// Header of the builder lists: logo, back to the Sylius admin, the shop (new tab) on the far right.
	// The Pages | Templates tabs are under the bar, in the content column (BuilderTabs).
	import BackLink from './BackLink.svelte';

	let { current }: { current: 'pages' | 'templates' } = $props();
</script>

<header class="yb-topbar">
	<img class="yb-logo" src="/yack/logo-yack.png" alt="Yack" />
	<BackLink href={current === 'pages' ? '/admin/yack/contenu/pages' : '/admin/yack/contenu/templates'} label="Retour à l'admin Sylius" />
	<span class="spacer"></span>
	<a class="yb-btn yb-btn-outline" href="/" target="_blank" rel="noopener">Voir le site</a>
</header>

<style>
	/* Barre du haut du back-office (.yb-topbar, admin.css). */
	.spacer {
		flex: 1;
	}
</style>
