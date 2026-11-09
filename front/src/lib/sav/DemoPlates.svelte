<script lang="ts">
	// « Plaques d'exemple pour la démonstration », under a photo form (/sav, /assistance). Without JavaScript, or with a
	// modifier key, a link downloads the plate. With JavaScript, a click loads the plate and hands it to `ontry`, which
	// puts it in the form and sends it, as if it had been photographed.
	import { demoPlateFileName, demoPlateUrl, type DemoPlate } from './nameplate.js';

	let {
		plates,
		test,
		ontry
	}: {
		plates: readonly DemoPlate[];
		/** Prefix of the `data-test-*` attributes (`sav-photo` gives `data-test-sav-photo-demo`). */
		test: string;
		/** The plate file, loaded; resolved once it is sent. */
		ontry: (file: File) => Promise<void>;
	} = $props();

	let loading: string | null = $state(null);
	/** Plate that could not be loaded: its link still downloads it. */
	let failed: string | null = $state(null);

	async function trying(event: MouseEvent, plate: DemoPlate) {
		if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
		// A click while a plate is loading is ignored, not turned into a download.
		event.preventDefault();
		if (loading) return;
		loading = plate.name;
		failed = null;
		try {
			const response = await fetch(demoPlateUrl(plate.name));
			if (!response.ok) throw new Error(`HTTP ${response.status}`);
			const blob = await response.blob();
			await ontry(new File([blob], demoPlateFileName(plate.name), { type: blob.type || 'image/png', lastModified: Date.now() }));
		} catch {
			failed = plate.name;
		} finally {
			loading = null;
		}
	}
</script>

<details class="yack-smart-panel__note" {...{ [`data-test-${test}-demo`]: true }}>
	<summary>Plaques d'exemple pour la démonstration</summary>
	<ul class="list-unstyled mb-0 mt-2">
		{#each plates as plate (plate.name)}
			<li>
				<a href={demoPlateUrl(plate.name)} download={demoPlateFileName(plate.name)} aria-busy={loading === plate.name} onclick={(event) => trying(event, plate)} {...{ [`data-test-${test}-demo-plate`]: plate.name }}>{plate.label}</a> : {plate.outcome}
				{#if failed === plate.name}<span class="d-block" role="alert">Plaque non chargée : téléchargez-la (clic droit), puis choisissez-la.</span>{/if}
			</li>
		{/each}
	</ul>
</details>
