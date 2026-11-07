<script lang="ts">
	// /assistance : manuels et notices officiels d'un appareil (#lib/assistance). La barre « Référence, modèle ou
	// n° de série » est un formulaire GET (?q=) : l'URL est partageable et tout marche sans JavaScript. `?ref=&sn=&marque=`
	// vient de l'écran de validation de la photo. Module coupé (YACK_ASSISTANCE=0) : 404 par getManuals.
	import { page } from '$app/state';
	import Breadcrumbs from '#lib/catalog/Breadcrumbs.svelte';
	import Icon from '#lib/layout/Icon.svelte';
	import ManualResults from '#lib/assistance/ManualResults.svelte';
	import { QUERY_MAX, searchInput } from '#lib/assistance/assistance.js';
	import { getManuals } from '#lib/remote/assistance.remote.js';

	const data = $derived(await getManuals(searchInput(page.url.searchParams)));
	const typed = $derived(data.input.q || data.input.ref || data.input.sn);
</script>

<svelte:head>
	<title>{typed ? `Manuels et notices : ${typed}` : 'Manuels et notices'} | Clik&amp;Yack</title>
	{#if typed}<meta name="robots" content="noindex" />{/if}
</svelte:head>

<div class="container py-4 py-lg-5 yack-assistance">
	<div class="mb-3"><Breadcrumbs items={[{ label: 'Accueil', url: '/' }, { label: 'Manuels et notices', url: null }]} /></div>

	<h1 class="mb-2">Manuels et notices</h1>
	<p class="yack-assistance__intro">
		Les manuels officiels d'un appareil : installation, utilisation, codes erreur. Saisissez la référence lue sur la plaque signalétique, le nom du modèle ou le n° de série.
	</p>

	<form method="get" action="/assistance" class="yack-assistance-search" role="search" data-test-assistance-search>
		<label class="form-label fw-bold" for="yack-assistance-q">Référence, modèle ou n° de série</label>
		<div class="yack-assistance-search__row">
			<input
				id="yack-assistance-q"
				type="search"
				name="q"
				value={typed}
				class="form-control"
				class:is-invalid={!!data.error}
				placeholder="ex. SRK25ZS-W ou YK19KAZ35S004567"
				maxlength={QUERY_MAX}
				required
				autocapitalize="characters"
				autocomplete="off"
				spellcheck="false"
				inputmode="text"
				aria-describedby={data.error ? 'yack-assistance-error' : undefined}
			/>
			<button class="btn btn-primary yack-assistance-search__submit" type="submit"><Icon name="search" />Chercher</button>
		</div>
		{#if data.error}
			<div class="invalid-feedback d-block" id="yack-assistance-error" role="alert" data-test-assistance-error>{data.error}</div>
		{/if}
	</form>

	{#if data.search}
		<ManualResults search={data.search} brand={data.input.marque} />
	{/if}
</div>
