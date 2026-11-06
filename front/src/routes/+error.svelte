<script lang="ts">
	import { page } from '$app/state';

	const notFound = $derived(page.status === 404);
</script>

<svelte:head>
	<title>{notFound ? 'Page introuvable' : 'Erreur'} | Clik&amp;Yack</title>
	<meta name="robots" content="noindex" />
</svelte:head>

<div class="container my-6 py-5 text-center" data-test-error={page.status}>
	<div class="display-1 fw-bold text-primary">{page.status}</div>
	<h1 class="h2 mb-3">{notFound ? 'Page introuvable' : 'Une erreur est survenue'}</h1>
	<p class="text-body-secondary mb-5">
		{#if notFound}
			La page demandée n'existe pas ou n'est plus en ligne.
		{:else}
			{page.error?.message ?? 'Le service est momentanément indisponible.'} Réessayez dans quelques instants.
		{/if}
	</p>
	{#if notFound}
		<form class="yack-search input-group mx-auto mb-5" style="max-width: 32rem;" method="get" action="/recherche" role="search">
			<input class="form-control" type="search" name="q" placeholder="Rechercher un produit, une référence…" aria-label="Rechercher" />
			<button class="btn btn-primary" type="submit">Rechercher</button>
		</form>
	{/if}
	<div class="d-flex flex-wrap gap-2 justify-content-center">
		<a class="btn btn-primary" href="/">Retour à l'accueil</a>
		<a class="btn btn-outline-primary" href="/produits">Clik&amp;Yack</a>
		<a class="btn btn-outline-primary" href="/pieces">Clik&amp;Yack Parts</a>
		<a class="btn btn-outline-primary" href="/sav">Espace SAV</a>
	</div>
</div>
