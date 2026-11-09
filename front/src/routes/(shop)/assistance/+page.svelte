<script lang="ts">
	// /assistance : manuels et notices officiels d'un appareil (#lib/assistance). La barre « Référence, modèle ou
	// n° de série » est un formulaire GET (?q=) : l'URL est partageable et tout marche sans JavaScript. Page vide : sous
	// la barre, l'encart photo (PlateCapture) ; sa lecture ouvre l'écran « C'est bien ça ? » (PlateValidation) au-dessus
	// de la barre, à la place de l'encart et des résultats, et la validation mène à `?ref=&sn=&marque=` : la barre porte
	// alors la référence (`ref`) et garde le n° de série en champ caché (`sn`), une nouvelle recherche ne le perd pas.
	// Résultats affichés : un lien discret « Nouvelle photo » ramène à l'encart de la page vide (`#photo-plaque`).
	// Page vide : les exemples qui donnent un résultat (AssistanceExamples) entre la barre et l'encart photo.
	// Module coupé (YACK_ASSISTANCE=0) : 404 par getManuals et readPlate.
	import { tick } from 'svelte';
	import { page } from '$app/state';
	import Breadcrumbs from '#lib/catalog/Breadcrumbs.svelte';
	import Icon from '#lib/layout/Icon.svelte';
	import AssistanceExamples from '#lib/assistance/AssistanceExamples.svelte';
	import ManualResults from '#lib/assistance/ManualResults.svelte';
	import PlateCapture from '#lib/assistance/PlateCapture.svelte';
	import PlateValidation from '#lib/assistance/PlateValidation.svelte';
	import { PLATE_ANCHOR, isEmptyInput, searchForm, searchInput, validationRetry } from '#lib/assistance/assistance.js';
	import type { PlateCheck } from '#lib/assistance/types.js';
	import { getManuals, readPlate } from '#lib/remote/assistance.remote.js';

	const data = $derived(await getManuals(searchInput(page.url.searchParams)));
	const typed = $derived(data.input.q || data.input.ref || data.input.sn);
	const form = $derived(searchForm(data.input));
	const describedBy = $derived([form.serial ? 'yack-assistance-serial-kept' : '', data.error ? 'yack-assistance-error' : ''].filter(Boolean).join(' ') || undefined);

	// Lecture de la plaque : même instance que l'encart photo. Une lecture écartée (« Reprendre la photo », validation
	// envoyée) ferme l'écran jusqu'à la lecture suivante.
	const plate = readPlate.for('assistance');
	let closed: PlateCheck | undefined = $state.raw();
	const reading = $derived(plate.result && plate.result !== closed ? plate.result : null);
	/** Validation renvoyée sans référence ni n° de série (sans JavaScript) : l'écran revient avec le message. */
	const retry = $derived(reading ? null : validationRetry(page.url.searchParams));
	const validating = $derived(reading !== null || retry !== null);

	/** Photo envoyée, montrée sur l'écran de validation (JavaScript seulement). */
	let photo: File | null = $state.raw(null);
	let preview: string | null = $state(null);
	$effect(() => {
		if (!photo) {
			preview = null;
			return;
		}
		const url = URL.createObjectURL(photo);
		preview = url;
		return () => URL.revokeObjectURL(url);
	});

	async function retake() {
		closed = plate.result;
		photo = null;
		await tick();
		document.getElementById(PLATE_ANCHOR)?.querySelector<HTMLButtonElement>('button:not([disabled])')?.focus();
	}
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

	{#if reading}
		<PlateValidation check={reading} {preview} onretake={retake} onsent={() => (closed = plate.result)} />
	{:else if retry}
		<PlateValidation check={retry.check} issues={retry.issues} />
	{/if}

	<form method="get" action="/assistance" class="yack-assistance-search" role="search" data-test-assistance-search>
		<label class="form-label fw-bold" for="yack-assistance-q">{form.serial ? 'Référence de l’appareil' : 'Référence, modèle ou n° de série'}</label>
		{#if form.serial}<input type="hidden" name="sn" value={form.serial} data-test-assistance-serial-kept />{/if}
		<div class="yack-assistance-search__row">
			<input
				id="yack-assistance-q"
				type="search"
				name={form.name}
				value={form.value}
				class="form-control"
				class:is-invalid={!!data.error}
				placeholder="ex. SRK25ZS-W ou YK19KAZ35S004567"
				maxlength={form.maxlength}
				required
				autocapitalize="characters"
				autocomplete="off"
				spellcheck="false"
				inputmode="text"
				aria-describedby={describedBy}
			/>
			<button class="btn btn-primary yack-assistance-search__submit" type="submit"><Icon name="search" />Chercher</button>
		</div>
		{#if form.serial}
			<p class="form-text yack-assistance-search__kept" id="yack-assistance-serial-kept">
				Avec le n° de série <span class="font-monospace">{form.serial}</span>. <a href="/assistance">Nouvelle recherche</a>
			</p>
		{/if}
		{#if data.error}
			<div class="invalid-feedback d-block" id="yack-assistance-error" role="alert" data-test-assistance-error>{data.error}</div>
		{/if}
		{#if !validating && !isEmptyInput(data.input)}
			<p class="yack-assistance-search__photo">
				<a href="/assistance#{PLATE_ANCHOR}" data-test-assistance-new-photo><Icon name="camera" />Nouvelle photo</a>
			</p>
		{/if}
	</form>

	{#if !validating}
		{#if isEmptyInput(data.input)}
			<AssistanceExamples title="Essayez avec une référence de démonstration" />
			<PlateCapture onchosen={(file) => (photo = file)} />
		{:else if data.search}
			<ManualResults search={data.search} brand={data.input.marque} />
		{/if}
	{/if}
</div>
