<script lang="ts">
	// « La pièce par une photo », under the serial number search of /sav: the photo of the rating plate or of the
	// label is read by the API (identifyFromPhoto, #lib/remote/sav.remote.ts). Known serial number: /sav/<serial>,
	// the same page as a manual search. Model reference of the catalog: its product sheet and documents. Otherwise a
	// notice (unreadable, unknown with a contact link, unavailable). Unreadable and unavailable end with the next step:
	// the serial number field of the page, or a demo serial number (NAMEPLATE_NEXT).
	// The file input, the camera and image buttons, the reduction in the browser and the preview: PhotoPicker. The
	// demo plates (DemoPlates) are sent as a photo would be.
	import SmartTag from '#lib/smart/SmartTag.svelte';
	import { identifyFromPhoto } from '#lib/remote/sav.remote.js';
	import DemoPlates from './DemoPlates.svelte';
	import { DEMO_PLATES, NAMEPLATE_ANCHOR, NAMEPLATE_NEXT, PHOTO_MESSAGES, readOnPlate } from './nameplate.js';
	import { savUrl } from './types.js';
	import PhotoPicker from './PhotoPicker.svelte';

	// Instance of this visit: the notice of a previous visit does not come back.
	const form = identifyFromPhoto.for('sav');
	const notice = $derived(form.result);
	const pending = $derived(form.pending > 0);
	// Issues without a field (API refusal): above the button.
	const formError = $derived(form.fields.allIssues()?.find((issue) => !issue.path || issue.path.length === 0)?.message);

	/** JavaScript runs: the submit button waits for a prepared photo. */
	let enhanced = $state(false);
	let preparing = $state(false);
	let localError: string | null = $state(null);
	let networkError: string | null = $state(null);
	/** A prepared photo is in the input. */
	let chosen = $state(false);
	let picker: ReturnType<typeof PhotoPicker> | undefined = $state();
	let formElement: HTMLFormElement | undefined = $state();

	const busy = $derived(preparing || pending);

	$effect(() => {
		enhanced = true;
	});

	function onchosen(file: File | null) {
		chosen = file !== null;
		if (!file) networkError = null;
	}

	/** « Saisir le numéro de série »: the field of the page takes the focus (the link alone only scrolls on some browsers). */
	function focusSerial(event: MouseEvent) {
		const field = document.getElementById(NAMEPLATE_NEXT.serialField);
		if (!field) return;
		event.preventDefault();
		field.scrollIntoView({ block: 'center' });
		field.focus();
	}

	/** Demo plate: chosen as a photo, then sent. */
	async function tryPlate(file: File) {
		networkError = null;
		if (await picker?.use(file)) formElement?.requestSubmit();
	}

	const enhance = form.enhance(async ({ submit }) => {
		networkError = null;
		if (!chosen) {
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

	<form {...enhance} bind:this={formElement} enctype="multipart/form-data" novalidate aria-busy={busy}>
		<PhotoPicker bind:this={picker} field={form.fields.photo} id="yack-sav-photo" test="sav-photo" busy={pending} bind:error={localError} bind:preparing {onchosen} />

		{#if formError || networkError}
			<div class="alert alert-danger mt-3 mb-0" role="alert" data-test-sav-photo-error>{formError ?? networkError}</div>
		{/if}

		<button type="submit" class="btn btn-primary yack-sav-photo__submit" disabled={busy || (enhanced && !chosen)} data-test-sav-photo-submit>
			{#if pending}
				<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Lecture de la plaque…
			{:else}
				Retrouver l'appareil
			{/if}
		</button>
		{#if pending}<span class="visually-hidden" role="status">Envoi et lecture de la photo en cours</span>{/if}
	</form>

	{#if notice}
		<div class="yack-smart-panel__result" role="status" data-test-sav-photo-result={notice.status}>
			<strong>{notice.message}</strong>
			{#if notice.product}
				<span class="d-block">{notice.product.brand ? `${notice.product.name} (${notice.product.brand})` : notice.product.name}</span>
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
					<span class="d-block small">Lu sur la plaque : {readOnPlate(notice)}</span>
				{/if}
				{#if notice.contactUrl}
					<a class="yack-smart-panel__source" href={notice.contactUrl} data-test-sav-photo-contact>Contacter le support Clik&amp;Yack</a>
				{/if}
				{#if notice.status === 'unreadable' || notice.status === 'unavailable'}
					<a class="yack-smart-panel__source" href="#{NAMEPLATE_NEXT.serialField}" onclick={focusSerial} data-test-sav-photo-type-serial>{NAMEPLATE_NEXT.type}</a>
					<a class="yack-smart-panel__source" href={savUrl(NAMEPLATE_NEXT.demoSerial)} data-test-sav-photo-demo-serial>{NAMEPLATE_NEXT.demo} {NAMEPLATE_NEXT.demoSerial}</a>
				{/if}
			{/if}
		</div>
	{/if}

	<DemoPlates plates={DEMO_PLATES} test="sav-photo" ontry={tryPlate} />
</section>
