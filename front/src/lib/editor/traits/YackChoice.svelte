<script lang="ts">
	// Segmented buttons of a block setting (D9): taille du titre, espaces, largeur, alignement, arrondi,
	// masquer. Choices: the trait's `options`, or a family of the charte (`charte`: titleSizes,
	// spacings, radii, from BuilderMe). `defaultId`: the choice that means « not set » (stored as '');
	// `withDefault`: no choice is lit while nothing is set, and the button « revenir au réglage par défaut »
	// next to the label (shown once a value is set) clears it, as in the mockup (front/static/ui-kit/panneau.html).
	// Title size and spacings also take a free value in px for the accounts allowed to leave the charte (a free
	// value equal to the charte becomes its key); for a Charte account the px field is shown locked.
	import { tick } from 'svelte';
	import { traitSetting, type TraitField, type TraitFieldProps } from './registerTraits.js';
	import type { StyleProperty } from '#lib/blocks/style.js';
	import { allowsFreeValues, FREE_KIND, normalizeFreeValue, PX_BOUNDS, warnsOnFreeValues, type Charte } from '#lib/editor/panels/blockStyle.js';
	import { rovingKeydown } from '#lib/editor/panels/roving.js';
	import { Lock, RotateCcw } from '#lib/editor/icons.js';
	import NoticeIcon from './NoticeIcon.svelte';

	type Choice = { id: string; label: string; title?: string };

	let { trait, value: initial, set, me, labelId }: TraitFieldProps = $props();

	// svelte-ignore state_referenced_locally
	let value = $state(initial);
	let typed = $state<string | null>(null);
	let error = $state<string | null>(null);
	let focusIndex = $state<number | null>(null);

	export const update: TraitField['update'] = (next) => {
		value = next;
		typed = null;
		error = null;
	};

	const label = $derived(trait.getLabel());
	const property = $derived(traitSetting<StyleProperty>(trait, 'styleProperty'));
	const defaultId = $derived(traitSetting<string>(trait, 'defaultId') ?? '');
	const family = $derived(traitSetting<keyof Charte>(trait, 'charte'));
	const choices = $derived.by((): Choice[] => {
		return family
			? me.charte[family].map((entry) => ({
					id: entry.key,
					label: family === 'radii' ? entry.label : entry.key.toUpperCase(),
					title: `${entry.label} (${entry.value})`
				}))
			: (traitSetting<Choice[]>(trait, 'options') ?? []);
	});
	/** '' shows the default choice. */
	const shown = $derived(value === '' ? defaultId : value);
	const current = $derived(choices.find((choice) => choice.id === shown) ?? null);
	const kind = $derived(property ? FREE_KIND[property] : undefined);
	const bounds = $derived(kind === 'title' || kind === 'spacing' ? PX_BOUNDS[kind] : null);
	const free = $derived(bounds !== null && allowsFreeValues(me.level));
	const offCharte = $derived(value !== '' && current === null);
	const selectedIndex = $derived(Math.max(0, choices.findIndex((choice) => choice.id === shown)));
	const id = $props.id();
	const tabStop = $derived(focusIndex ?? selectedIndex);

	function pick(id: string) {
		error = null;
		typed = null;
		const next = id === defaultId ? '' : id;
		if (next === value) return;
		value = next;
		set(next);
	}

	function applyFree(input: string) {
		if (kind !== 'title' && kind !== 'spacing') return;
		if (input.trim() === '') return;
		const result = normalizeFreeValue(kind, input, me.charte);
		if (!result.ok) {
			error = result.message;
			return;
		}
		error = null;
		typed = null;
		if (result.value === value) return;
		value = result.value;
		set(result.value);
	}

	/** The reset button leaves with the value: the focus goes to the choice now in use, not to the page. */
	let bar = $state<HTMLElement>();
	async function reset() {
		pick(defaultId);
		await tick();
		bar?.querySelector<HTMLElement>('[data-roving][tabindex="0"]')?.focus();
	}
</script>

<div class="ui-row yb-choice">
	<div class="ui-label-cell">
		<span class="ui-label" id={labelId}>{label}</span>
		{#if value !== ''}
			<button type="button" class="ui-reset" aria-label="{label} : revenir au réglage par défaut" data-tip="Par défaut" onclick={reset}
				><RotateCcw class="ui-ic" /></button
			>
		{/if}
	</div>
	<div class="ui-control ui-inline">
		<div
			class="ui-seg"
			bind:this={bar}
			role="toolbar"
			aria-labelledby={labelId}
			tabindex="-1"
			onkeydown={(event) => {
				const next = rovingKeydown(event, tabStop);
				if (next !== null) focusIndex = next;
			}}
			onfocusout={() => (focusIndex = null)}
		>
			{#each choices as choice, index (choice.id)}
				<button
					type="button"
					data-roving
					aria-pressed={choice.id === shown}
					aria-label={choice.title && choice.title !== choice.label ? `${choice.label}, ${choice.title}` : undefined}
					data-tip={choice.title}
					tabindex={index === tabStop ? 0 : -1}
					onclick={() => pick(choice.id)}>{choice.label}</button
				>
			{/each}
		</div>

		{#if bounds && free}
			<input
				class="ui-input ui-input-num ui-px"
				type="number"
				min={bounds.min}
				max={bounds.max}
				step="1"
				inputmode="numeric"
				placeholder="px"
				aria-label="{label} : valeur libre en pixels, de {bounds.min} à {bounds.max}"
				aria-invalid={error ? 'true' : undefined}
				aria-describedby={error ? `${id}-error` : undefined}
				value={typed ?? (offCharte ? value.replace(/px$/, '') : '')}
				oninput={(event) => (typed = event.currentTarget.value)}
				onchange={(event) => applyFree(event.currentTarget.value)}
				onkeydown={(event) => {
					if (event.key === 'Enter') applyFree(event.currentTarget.value);
				}}
			/>
		{:else if bounds}
			<span class="ui-field ui-px is-disabled" title="Votre niveau (Charte) ne permet pas de valeur libre"
				><Lock class="ui-ic ui-ic-sm" /><span class="ui-sr-only">{label} : valeur libre en pixels, indisponible au niveau Charte</span></span
			>
		{/if}
	</div>
	{#if error}<small class="ui-error ui-full" id="{id}-error" role="alert">{error}</small>{/if}

	{#if offCharte}
		{#if !allowsFreeValues(me.level)}
			<p class="ui-note ui-full" role="status">
				<NoticeIcon /><span>Valeur actuelle hors charte : {value}. Votre compte ne permet pas d'en poser : choisissez une valeur de la charte pour la remplacer, ou laissez-la.</span>
			</p>
		{:else if warnsOnFreeValues(me.level)}
			<p class="ui-banner ui-full" role="status"><NoticeIcon kind="warning" /><span>Hors charte : cette valeur n'est pas dans la charte Yack</span></p>
		{/if}
	{/if}
</div>
