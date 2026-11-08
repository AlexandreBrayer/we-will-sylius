<script lang="ts">
	// Encart photo de /assistance, sous la barre de recherche : la photo de la plaque signalétique ou de la fiche
	// technique part au formulaire distant `readPlate` (#lib/remote/assistance.remote.ts), qui rend l'écran « C'est bien
	// ça ? » (PlateValidation, posé par la page). Prise de photo, réduction dans le navigateur et aperçu :
	// #lib/sav/PhotoPicker. Sans JavaScript : un champ fichier, le fichier part tel quel, l'écran est rendu par le serveur.
	// `{'\n'}` : le formulaire commence une ligne de HTML (son action se lit ligne à ligne par grep).
	import Icon from '#lib/layout/Icon.svelte';
	import SmartTag from '#lib/smart/SmartTag.svelte';
	import PhotoPicker from '#lib/sav/PhotoPicker.svelte';
	import { PHOTO_MESSAGES, demoPlateUrl } from '#lib/sav/nameplate.js';
	import { readPlate } from '#lib/remote/assistance.remote.js';
	import { PLATE_ANCHOR, PLATE_DEMOS } from './assistance.js';

	/** `onchosen` : la photo prête à partir (pour son aperçu sur l'écran de validation), null sinon. */
	let { onchosen }: { onchosen?: (file: File | null) => void } = $props();

	// Même instance que la page, qui lit son résultat.
	const form = readPlate.for('assistance');
	const pending = $derived(form.pending > 0);
	// Refus de l'API sans champ (422) : au-dessus du bouton.
	const formError = $derived(form.fields.allIssues()?.find((issue) => !issue.path || issue.path.length === 0)?.message);

	/** JavaScript actif : le bouton d'envoi attend une photo préparée. */
	let enhanced = $state(false);
	let preparing = $state(false);
	let localError: string | null = $state(null);
	let networkError: string | null = $state(null);
	let chosen = $state(false);

	const busy = $derived(preparing || pending);

	$effect(() => {
		enhanced = true;
	});

	function chose(file: File | null) {
		chosen = file !== null;
		if (!file) networkError = null;
		onchosen?.(file);
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

<section class="yack-smart-panel yack-assistance-photo" id={PLATE_ANCHOR} aria-labelledby="yack-assistance-photo-title" data-test-assistance-photo>
	<div class="yack-smart-panel__head">
		<h2 class="yack-smart-panel__title" id="yack-assistance-photo-title">Ou par une photo</h2>
		<SmartTag />
	</div>
	<p class="yack-smart-panel__why">
		Prenez une photo de la plaque signalétique ou de la fiche technique. La marque, la référence et le n° de série lus vous sont montrés pour vérification avant la recherche.
	</p>

	{'\n'}<form {...enhance} enctype="multipart/form-data" novalidate aria-busy={busy} data-test-assistance-photo-form>
		<PhotoPicker field={form.fields.photo} id="yack-assistance-photo" test="assistance-photo" busy={pending} bind:error={localError} bind:preparing onchosen={chose} />

		{#if formError || networkError}
			<div class="alert alert-danger mt-3 mb-0" role="alert" data-test-assistance-photo-error>{formError ?? networkError}</div>
		{/if}

		<button type="submit" class="btn yack-smart-btn yack-sav-photo__submit" disabled={busy || (enhanced && !chosen)} data-test-assistance-photo-submit>
			{#if pending}
				<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Lecture de la plaque…
			{:else}
				<Icon name="sparkles" />Lire la plaque
			{/if}
		</button>
		{#if pending}<span class="visually-hidden" role="status">Envoi et lecture de la photo en cours</span>{/if}
	</form>

	<details class="yack-smart-panel__note" data-test-assistance-photo-demo>
		<summary>Plaques d'exemple pour la démonstration</summary>
		<ul class="list-unstyled mb-0 mt-2">
			{#each PLATE_DEMOS as plate (plate.name)}
				<li><a href={demoPlateUrl(plate.name)} download>{plate.label}</a> : {plate.outcome}</li>
			{/each}
		</ul>
	</details>
</section>
