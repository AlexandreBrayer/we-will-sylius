<script lang="ts">
	// « La pièce par une photo » (IA-05), under the serial number search of /sav: the photo of the rating plate
	// is read by the API (identifyFromPhoto, #lib/remote/sav.remote.ts). Found: /sav/<serial>, the same page as a
	// manual search. Otherwise a notice (unreadable, unknown with a contact link, unavailable). Works without JS.
	import Icon from '#lib/layout/Icon.svelte';
	import SmartTag from '#lib/smart/SmartTag.svelte';
	import { identifyFromPhoto } from '#lib/remote/sav.remote.js';
	import { DEMO_UNKNOWN_SERIAL, NAMEPLATE_ACCEPT, demoPlateUrl } from './nameplate.js';
	import { DEMO_SERIALS } from './types.js';

	// Instance of this visit: the notice of a previous visit does not come back.
	const form = identifyFromPhoto.for('sav');
	const notice = $derived(form.result);
	const photoError = $derived(form.fields.photo.issues()?.[0]?.message);
	// Issues without a field (API refusal): above the button.
	const formError = $derived(form.fields.allIssues()?.find((issue) => !issue.path || issue.path.length === 0)?.message);
	const pending = $derived(form.pending > 0);
</script>

<section class="yack-smart-panel" aria-labelledby="yack-sav-photo-title" data-test-sav-photo>
	<div class="yack-smart-panel__head">
		<h2 class="yack-smart-panel__title" id="yack-sav-photo-title">La pièce par une photo</h2>
		<SmartTag simulated={notice?.simulated === true} />
	</div>
	<p class="yack-smart-panel__why">Une photo de la plaque signalétique retrouve l'appareil et ouvre la bonne vue éclatée.</p>

	<form {...form} enctype="multipart/form-data" novalidate>
		<label class="form-label fw-bold" for="yack-sav-photo">Photographier la plaque (JPEG ou PNG)</label>
		<input
			{...form.fields.photo.as('file')}
			id="yack-sav-photo"
			class="form-control"
			class:is-invalid={!!photoError}
			accept={NAMEPLATE_ACCEPT}
			capture="environment"
			required
			aria-describedby={photoError ? 'yack-sav-photo-error' : undefined}
			data-test-sav-photo-input
		/>
		{#if photoError}<div class="invalid-feedback d-block" id="yack-sav-photo-error" data-test-field-error="photo">{photoError}</div>{/if}
		{#if formError}<div class="alert alert-danger mt-3 mb-0" role="alert" data-test-sav-photo-error>{formError}</div>{/if}
		<button type="submit" class="btn yack-smart-btn mt-3" disabled={pending} data-test-sav-photo-submit>
			<Icon name="camera" />
			{pending ? 'Lecture de la plaque…' : "Retrouver l'appareil"}
		</button>
	</form>

	{#if notice}
		<div class="yack-smart-panel__result" role="status" data-test-sav-photo-result={notice.status}>
			<strong>{notice.message}</strong>
			{#if notice.serialNumber}
				<span class="d-block small">Numéro lu sur la plaque : <span class="font-monospace">{notice.serialNumber}</span>{#if notice.model}, {notice.model}{/if}</span>
			{/if}
			{#if notice.contactUrl}
				<a class="yack-smart-panel__source" href={notice.contactUrl} data-test-sav-photo-contact>Contacter le support Clik&amp;Yack</a>
			{/if}
		</div>
	{/if}

	<details class="yack-smart-panel__note" data-test-sav-photo-demo>
		<summary>Plaques d'exemple pour la démonstration</summary>
		<ul class="list-unstyled mb-0 mt-2">
			{#each [...DEMO_SERIALS, DEMO_UNKNOWN_SERIAL] as serial (serial)}
				<li>
					<a href={demoPlateUrl(serial)} download>{serial}</a>{#if serial === DEMO_UNKNOWN_SERIAL}
						(absent du parc : « Appareil non trouvé »){/if}
				</li>
			{/each}
		</ul>
	</details>
</section>
