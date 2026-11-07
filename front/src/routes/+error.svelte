<script lang="ts">
	// Error page inside the shop chrome (404, 5xx), at the demo's design: code in magenta, title, text,
	// "Retour à l'accueil" and "Voir nos produits"; the 404 also offers the search (pill, _sav.scss).
	import { page } from '$app/state';
	import Icon from '#lib/layout/Icon.svelte';

	const notFound = $derived(page.status === 404);
</script>

<svelte:head>
	<title>{notFound ? 'Page introuvable' : 'Erreur'} | Clik&amp;Yack</title>
	<meta name="robots" content="noindex" />
</svelte:head>

<div class="container my-5">
	<div class="yack-empty" data-test-error={page.status}>
		<div class="display-1 fw-bold text-primary lh-1 mb-3">{page.status}</div>
		<h1 class="h2">{notFound ? 'Page introuvable' : 'Une erreur est survenue'}</h1>
		<p class="mb-4">
			{#if notFound}
				La page demandée n'existe pas ou a été déplacée.
			{:else}
				{page.error?.message ?? 'Le service est momentanément indisponible.'} Réessayez dans quelques instants.
			{/if}
		</p>
		{#if notFound}
			<form class="yack-pill-search mx-auto mb-4" method="get" action="/recherche" role="search">
				<span class="yack-pill-search__icon"><Icon name="search" /></span>
				<input class="form-control" type="search" name="q" placeholder="Rechercher un produit, une référence…" aria-label="Rechercher" />
				<button class="btn btn-primary" type="submit">Rechercher</button>
			</form>
		{/if}
		<div class="yack-actions-center">
			<a class="btn btn-primary" href="/">Retour à l'accueil</a>
			<a class="btn btn-ghost" href="/produits">Voir nos produits</a>
		</div>
		{#if notFound}
			<ul class="yack-chips justify-content-center mt-4">
				<li><a class="yack-chip" href="/pieces">Pièces détachées</a></li>
				<li><a class="yack-chip" href="/sav">Espace SAV</a></li>
				<li><a class="yack-chip" href="/trouver-un-installateur">Trouver un installateur</a></li>
			</ul>
		{/if}
	</div>
</div>
