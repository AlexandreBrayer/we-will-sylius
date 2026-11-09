<script lang="ts">
	// Écran « C'est bien ça ? » de /assistance, au-dessus de la barre : ce que la photo a donné (ou rien : plaque
	// illisible, lecture indisponible) en champs corrigibles, avec l'origine de chaque valeur et la désignation lue.
	// Formulaire GET vers /assistance?ref=&sn=&marque= : il marche sans JavaScript (un envoi sans référence ni n° de
	// série revient sur cet écran, `validationRetry`). Avec JavaScript : contrôle avant l'envoi (`validateFields`),
	// message sur le champ sans navigation, puis l'URL des résultats sans champ vide (`assistanceUrl`). Rien de lu (plaque
	// illisible, lecture indisponible) : sous les champs, les exemples de démonstration (AssistanceExamples).
	import { goto } from '$app/navigation';
	import { onMount, tick } from 'svelte';
	import Icon from '#lib/layout/Icon.svelte';
	import SmartTag from '#lib/smart/SmartTag.svelte';
	import AssistanceExamples from './AssistanceExamples.svelte';
	import { BRAND_MAX, PLATE_ANCHOR, REFERENCE_MAX, SERIAL_MAX, assistanceUrl, validateFields, type FieldIssues } from './assistance.js';
	import type { PlateCheck, PlateValues } from './types.js';

	let {
		check,
		issues = {},
		preview = null,
		onretake,
		onsent
	}: {
		check: PlateCheck;
		/** Problèmes des champs rendus par le serveur (validation renvoyée vide sans JavaScript). */
		issues?: FieldIssues;
		/** Aperçu de la photo envoyée (JavaScript seulement). */
		preview?: string | null;
		/** « Reprendre la photo » sans quitter la page ; absent : lien vers l'encart photo. */
		onretake?: () => void;
		/** La validation est partie vers les résultats. */
		onsent?: () => void;
	} = $props();

	/** Problèmes trouvés dans le navigateur, à la place de ceux du serveur une fois le formulaire envoyé. */
	let checked: FieldIssues | null = $state(null);
	const shown = $derived(checked ?? issues);

	const FIELDS: { name: keyof PlateValues; label: string; maxlength: number }[] = [
		{ name: 'marque', label: 'Marque', maxlength: BRAND_MAX },
		{ name: 'ref', label: 'Référence', maxlength: REFERENCE_MAX },
		{ name: 'sn', label: 'N° de série', maxlength: SERIAL_MAX }
	];

	let heading: HTMLElement | undefined = $state();
	let fields: Partial<Record<keyof PlateValues, HTMLInputElement>> = $state({});

	// L'écran vient de s'ouvrir à la place de l'encart photo : le lecteur d'écran et le regard y vont.
	onMount(() => heading?.focus());

	const describedBy = (name: keyof PlateValues): string | undefined =>
		[check.notes[name] ? `yack-assistance-plate-${name}-note` : '', shown[name] ? `yack-assistance-plate-${name}-error` : ''].filter(Boolean).join(' ') || undefined;

	async function submit(event: SubmitEvent & { currentTarget: HTMLFormElement }) {
		event.preventDefault();
		const data = new FormData(event.currentTarget);
		const values: PlateValues = { ref: String(data.get('ref') ?? ''), sn: String(data.get('sn') ?? ''), marque: String(data.get('marque') ?? '') };
		const found = validateFields(values);
		checked = found;
		const invalid = FIELDS.find((field) => found[field.name]);
		if (invalid) {
			await tick();
			fields[invalid.name]?.focus();
			return;
		}
		await goto(assistanceUrl(values));
		onsent?.();
	}

	function retake(event: MouseEvent) {
		if (!onretake) return;
		event.preventDefault();
		onretake();
	}
</script>

<section class="yack-smart-panel yack-assistance-plate" aria-labelledby="yack-assistance-plate-title" data-test-assistance-validation={check.status}>
	<div class="yack-smart-panel__head">
		<h2 class="yack-smart-panel__title" id="yack-assistance-plate-title" tabindex="-1" bind:this={heading}>C'est bien ça ?</h2>
		<SmartTag simulated={check.simulated} />
	</div>

	{#if check.message}
		<p class="alert alert-warning yack-assistance-plate__message" role="alert" data-test-assistance-plate-message={check.status}>{check.message}</p>
	{:else}
		<p class="yack-smart-panel__why">Vérifiez ce qui a été lu et corrigez si besoin : la recherche des manuels part de ces valeurs.</p>
	{/if}

	{#if preview || check.model}
		<div class="yack-assistance-plate__read">
			{#if preview}<img class="yack-assistance-plate__photo" src={preview} alt="Plaque envoyée pour la lecture" data-test-assistance-plate-photo />{/if}
			{#if check.model}<p class="yack-assistance-plate__model" data-test-assistance-plate-model>Désignation lue : <strong>{check.model}</strong></p>{/if}
		</div>
	{/if}

	<form method="get" action="/assistance" class="yack-assistance-plate__form" novalidate onsubmit={submit} data-test-assistance-validation-form>
		{#each FIELDS as field (field.name)}
			<div class="yack-assistance-plate__field">
				<label class="form-label fw-bold" for="yack-assistance-plate-{field.name}">{field.label}</label>
				<input
					bind:this={fields[field.name]}
					id="yack-assistance-plate-{field.name}"
					type="text"
					name={field.name}
					value={check.values[field.name]}
					class="form-control"
					class:is-invalid={!!shown[field.name]}
					maxlength={field.maxlength}
					autocapitalize="characters"
					autocomplete="off"
					spellcheck="false"
					inputmode="text"
					aria-invalid={shown[field.name] ? 'true' : undefined}
					aria-describedby={describedBy(field.name)}
					data-test-assistance-plate-field={field.name}
				/>
				{#if check.notes[field.name]}
					<p class="form-text yack-assistance-plate__note" id="yack-assistance-plate-{field.name}-note" data-test-assistance-plate-note={field.name}>{check.notes[field.name]}</p>
				{/if}
				{#if shown[field.name]}
					<div class="invalid-feedback d-block" id="yack-assistance-plate-{field.name}-error" role="alert" data-test-field-error={field.name}>{shown[field.name]}</div>
				{/if}
			</div>
		{/each}

		<div class="yack-assistance-plate__actions">
			<button type="submit" class="btn btn-primary yack-assistance-plate__confirm" data-test-assistance-confirm><Icon name="check" />C'est bien ça, voir les manuels</button>
			<a class="btn btn-outline-secondary yack-assistance-plate__retake" href="/assistance#{PLATE_ANCHOR}" onclick={retake} data-test-assistance-retake><Icon name="camera" />Reprendre la photo</a>
		</div>
	</form>

	{#if check.status === 'unavailable' || check.status === 'unreadable'}
		<AssistanceExamples title="Ou essayez une référence de démonstration" />
	{/if}
</section>
