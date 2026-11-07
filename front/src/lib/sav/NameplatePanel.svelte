<script lang="ts">
	// « La pièce par une photo », under the serial number search of /sav: the photo of the rating plate or of the
	// label is read by the API (identifyFromPhoto, #lib/remote/sav.remote.ts). Known serial number: /sav/<serial>,
	// the same page as a manual search. Model reference of the catalog: its product sheet and documents. Otherwise a
	// notice (unreadable, unknown with a contact link, unavailable).
	// Without JavaScript: one file input (the camera on a phone, `capture`), the file is sent as chosen. With
	// JavaScript: « Prendre une photo » (touch screens) and « Choisir une image », the photo reduced in the browser
	// (#lib/sav/photo.ts), a preview, busy states while preparing, sending and reading.
	import { onDestroy } from 'svelte';
	import Icon from '#lib/layout/Icon.svelte';
	import SmartTag from '#lib/smart/SmartTag.svelte';
	import { identifyFromPhoto } from '#lib/remote/sav.remote.js';
	import { DEMO_PLATES, NAMEPLATE_ACCEPT, NAMEPLATE_ANCHOR, PHOTO_MESSAGES, demoPlateUrl, formatBytes, photoIssue } from './nameplate.js';
	import { preparePhoto, replaceFile } from './photo.js';

	// Instance of this visit: the notice of a previous visit does not come back.
	const form = identifyFromPhoto.for('sav');
	const notice = $derived(form.result);
	const pending = $derived(form.pending > 0);
	// Issues without a field (API refusal): above the button.
	const formError = $derived(form.fields.allIssues()?.find((issue) => !issue.path || issue.path.length === 0)?.message);

	let input: HTMLInputElement | undefined = $state();
	/** JavaScript runs: the two buttons replace the bare input. */
	let enhanced = $state(false);
	/** Touch screen: the camera button comes first. */
	let touch = $state(false);
	let preparing = $state(false);
	let localError: string | null = $state(null);
	let networkError: string | null = $state(null);
	let chosen: { name: string; size: number; reduced: boolean; preview: string } | null = $state(null);

	const busy = $derived(preparing || pending);
	const photoError = $derived(localError ?? form.fields.photo.issues()?.[0]?.message ?? null);

	$effect(() => {
		enhanced = true;
		touch = window.matchMedia('(pointer: coarse)').matches;
	});

	onDestroy(() => forget());

	function forget() {
		if (chosen) URL.revokeObjectURL(chosen.preview);
		chosen = null;
	}

	/** Opens the camera (`capture`) or the file chooser, on the same input. */
	function pick(camera: boolean) {
		if (!input || busy) return;
		if (camera) input.setAttribute('capture', 'environment');
		else input.removeAttribute('capture');
		input.click();
	}

	/** Keeps the remote form in step with the file of the input (reduced or removed). */
	function sync(field: HTMLInputElement) {
		field.dispatchEvent(new Event('input', { bubbles: true }));
	}

	async function onPhoto(event: Event) {
		if (!enhanced) return;
		const field = event.currentTarget as HTMLInputElement;
		const file = field.files?.[0];
		forget();
		localError = null;
		networkError = null;
		if (!file) return;

		preparing = true;
		try {
			const prepared = await preparePhoto(file);
			if ('error' in prepared) {
				localError = prepared.error;
				field.value = '';
				sync(field);
				return;
			}
			let sent = prepared.file;
			if (sent !== file && replaceFile(field, sent)) {
				sync(field);
			} else if (sent !== file) {
				// The browser keeps the original: sent as is when the API reads it.
				sent = file;
				const issue = photoIssue(file);
				if (issue) {
					localError = issue;
					field.value = '';
					sync(field);
					return;
				}
			}
			chosen = { name: sent.name, size: sent.size, reduced: sent !== file, preview: URL.createObjectURL(sent) };
		} finally {
			preparing = false;
		}
	}

	const enhance = form.enhance(async ({ submit }) => {
		networkError = null;
		if (!input?.files?.length) {
			localError = PHOTO_MESSAGES.missing;
			return;
		}
		try {
			await submit();
		} catch {
			networkError = PHOTO_MESSAGES.network;
		}
	});
</script>

<section class="yack-smart-panel yack-sav-photo" id={NAMEPLATE_ANCHOR} aria-labelledby="yack-sav-photo-title" data-test-sav-photo>
	<div class="yack-smart-panel__head">
		<h2 class="yack-smart-panel__title" id="yack-sav-photo-title">La pièce par une photo</h2>
		<SmartTag simulated={notice?.simulated === true} />
	</div>
	<p class="yack-smart-panel__why">
		Une photo de la plaque signalétique ou de l'étiquette retrouve l'appareil et ouvre la bonne vue éclatée, ou la fiche du modèle.
	</p>

	<form {...enhance} enctype="multipart/form-data" novalidate aria-busy={busy} onreset={() => forget()}>
		<label class="form-label fw-bold" class:visually-hidden={enhanced} for="yack-sav-photo">Photographier la plaque</label>
		<input
			{...form.fields.photo.as('file')}
			bind:this={input}
			id="yack-sav-photo"
			class="form-control"
			class:visually-hidden={enhanced}
			class:is-invalid={!!photoError}
			accept={NAMEPLATE_ACCEPT}
			capture="environment"
			required
			tabindex={enhanced ? -1 : undefined}
			aria-describedby={photoError ? 'yack-sav-photo-error' : undefined}
			onchange={onPhoto}
			data-test-sav-photo-input
		/>

		{#if enhanced}
			<div class="yack-sav-photo__pick">
				{#if touch}
					<button type="button" class="btn yack-smart-btn" disabled={busy} onclick={() => pick(true)} data-test-sav-photo-camera>
						<Icon name="camera" />{chosen ? 'Reprendre la photo' : 'Prendre une photo'}
					</button>
				{/if}
				<button type="button" class="btn btn-outline-secondary yack-sav-photo__choose" disabled={busy} onclick={() => pick(false)} data-test-sav-photo-choose>
					{chosen && !touch ? 'Choisir une autre image' : 'Choisir une image'}
				</button>
			</div>
		{/if}

		{#if preparing}
			<p class="yack-sav-photo__status" role="status"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Préparation de la photo…</p>
		{:else if chosen}
			<div class="yack-sav-photo__chosen" data-test-sav-photo-chosen>
				<img class="yack-sav-photo__preview" src={chosen.preview} alt="Aperçu de la plaque choisie" />
				<span class="yack-sav-photo__file">
					<span class="text-break">{chosen.name}</span>
					<span class="d-block small">{formatBytes(chosen.size)}{chosen.reduced ? ', réduite pour l’envoi' : ''}</span>
				</span>
			</div>
		{/if}

		{#if photoError}<div class="invalid-feedback d-block" id="yack-sav-photo-error" role="alert" data-test-field-error="photo">{photoError}</div>{/if}
		{#if formError || networkError}
			<div class="alert alert-danger mt-3 mb-0" role="alert" data-test-sav-photo-error>{formError ?? networkError}</div>
		{/if}

		<button type="submit" class="btn yack-smart-btn yack-sav-photo__submit" disabled={busy || (enhanced && !chosen)} data-test-sav-photo-submit>
			{#if pending}
				<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Lecture de la plaque…
			{:else}
				<Icon name="sparkles" />Retrouver l'appareil
			{/if}
		</button>
		{#if pending}<span class="visually-hidden" role="status">Envoi et lecture de la photo en cours</span>{/if}
	</form>

	{#if notice}
		<div class="yack-smart-panel__result" role="status" data-test-sav-photo-result={notice.status}>
			<strong>{notice.message}</strong>
			{#if notice.product}
				<span class="d-block">
					{notice.product.name}{#if notice.product.brand} ({notice.product.brand}){/if}
				</span>
				{#if notice.serialNumber}
					<span class="d-block small">Numéro de série <span class="font-monospace">{notice.serialNumber}</span> absent du parc : la vue éclatée demande un appareil enregistré.</span>
				{/if}
				<a class="yack-smart-panel__source" href={notice.product.url} data-test-sav-photo-product>Voir la fiche produit {notice.product.code}</a>
				{#if notice.product.documents.length > 0}
					<span class="d-block small mt-2">Documentation du modèle chez Yack :</span>
					<ul class="yack-sav-photo__docs" data-test-sav-photo-documents>
						{#each notice.product.documents as document (document)}<li>{document}</li>{/each}
					</ul>
				{/if}
			{:else}
				{#if notice.serialNumber || notice.reference}
					<span class="d-block small">
						Lu sur la plaque :
						{#if notice.serialNumber}n° <span class="font-monospace">{notice.serialNumber}</span>{/if}{#if notice.serialNumber && notice.reference},{/if}
						{#if notice.reference}réf. <span class="font-monospace">{notice.reference}</span>{/if}{#if notice.model}, {notice.model}{/if}
					</span>
				{/if}
				{#if notice.contactUrl}
					<a class="yack-smart-panel__source" href={notice.contactUrl} data-test-sav-photo-contact>Contacter le support Clik&amp;Yack</a>
				{/if}
			{/if}
		</div>
	{/if}

	<details class="yack-smart-panel__note" data-test-sav-photo-demo>
		<summary>Plaques d'exemple pour la démonstration</summary>
		<ul class="list-unstyled mb-0 mt-2">
			{#each DEMO_PLATES as plate (plate.name)}
				<li><a href={demoPlateUrl(plate.name)} download>{plate.label}</a> : {plate.outcome}</li>
			{/each}
		</ul>
	</details>
</section>
