<script lang="ts">
	// Segmented buttons of a block setting (D9): taille du titre, espaces, largeur, alignement, arrondi,
	// masquer. Choices: the trait's `options`, or a family of the charte (`charte`: titleSizes,
	// spacings, radii, from BuilderMe). `defaultId`: the choice that means « not set » (stored as '');
	// `withDefault`: a « Par défaut » choice first. Title size and spacings also take a free value in px
	// for the accounts allowed to leave the charte; a free value equal to the charte becomes its key.
	import { traitSetting, type TraitField, type TraitFieldProps } from './registerTraits.js';
	import type { StyleProperty } from '#lib/blocks/style.js';
	import { allowsFreeValues, FREE_KIND, normalizeFreeValue, PX_BOUNDS, warnsOnFreeValues, type Charte } from '#lib/editor/panels/blockStyle.js';
	import { rovingKeydown } from '#lib/editor/panels/roving.js';

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
		const own: Choice[] = family
			? me.charte[family].map((entry) => ({
					id: entry.key,
					label: family === 'radii' ? entry.label : entry.key.toUpperCase(),
					title: `${entry.label} (${entry.value})`
				}))
			: (traitSetting<Choice[]>(trait, 'options') ?? []);
		return traitSetting<boolean>(trait, 'withDefault') ? [{ id: '', label: 'Par défaut', title: 'Réglage prévu par le bloc' }, ...own] : own;
	});
	/** '' shows the default choice. */
	const shown = $derived(value === '' ? defaultId : value);
	const current = $derived(choices.find((choice) => choice.id === shown) ?? null);
	const kind = $derived(property ? FREE_KIND[property] : undefined);
	const bounds = $derived(kind === 'title' || kind === 'spacing' ? PX_BOUNDS[kind] : null);
	const free = $derived(bounds !== null && allowsFreeValues(me.level));
	const offCharte = $derived(value !== '' && current === null);
	const selectedIndex = $derived(Math.max(0, choices.findIndex((choice) => choice.id === shown)));
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
</script>

<div class="yb-choice">
	<div class="yb-trait-label" id={labelId}>{label}{#if offCharte}<span class="current"> : {value}</span>{/if}</div>
	<div
		class="segments"
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
				class:selected={choice.id === shown}
				aria-pressed={choice.id === shown}
				aria-label={choice.title && choice.title !== choice.label ? `${choice.label}, ${choice.title}` : undefined}
				title={choice.title}
				tabindex={index === tabStop ? 0 : -1}
				onclick={() => pick(choice.id)}>{choice.label}</button
			>
		{/each}
	</div>

	{#if free && bounds}
		<label class="free">
			<span>Valeur libre</span>
			<input
				type="number"
				min={bounds.min}
				max={bounds.max}
				step="1"
				inputmode="numeric"
				aria-label="{label} : valeur libre en pixels, de {bounds.min} à {bounds.max}"
				aria-invalid={error ? 'true' : undefined}
				value={typed ?? (offCharte ? value.replace(/px$/, '') : '')}
				oninput={(event) => (typed = event.currentTarget.value)}
				onchange={(event) => applyFree(event.currentTarget.value)}
				onkeydown={(event) => {
					if (event.key === 'Enter') applyFree(event.currentTarget.value);
				}}
			/>
			<span>px</span>
		</label>
	{/if}
	{#if error}<small class="yb-trait-error" role="alert">{error}</small>{/if}

	{#if offCharte}
		{#if !allowsFreeValues(me.level)}
			<p class="yb-trait-note" role="status">
				Valeur actuelle hors charte : {value}. Votre compte ne permet pas d'en poser : choisissez une valeur de la charte pour la remplacer, ou laissez-la.
			</p>
		{:else if warnsOnFreeValues(me.level)}
			<p class="yb-trait-warning" role="status">Hors charte : cette valeur n'est pas dans la charte Yack</p>
		{/if}
	{/if}
</div>

<style>
	.yb-choice {
		display: grid;
		gap: 0.45rem;
	}
	.current {
		font-weight: 400;
		color: var(--yack-gray);
	}
	.segments {
		display: flex;
		flex-wrap: wrap;
		border: 1px solid var(--yack-border);
		border-radius: 0.375rem;
		overflow: hidden;
	}
	.segments button {
		flex: 1 0 auto;
		padding: 0.4rem 0.5rem;
		font: inherit;
		font-size: 0.8rem;
		color: var(--yack-dark);
		background: transparent;
		border: 0;
		border-right: 1px solid var(--yack-border);
		cursor: pointer;
	}
	.segments button:last-child {
		border-right: 0;
	}
	.segments button.selected {
		background: color-mix(in srgb, var(--yack-magenta) 14%, transparent);
		color: var(--yack-magenta-dark);
		box-shadow: inset 0 -3px 0 var(--yack-magenta);
		font-weight: 600;
	}
	.segments button:focus-visible {
		outline: 2px solid var(--yack-dark);
		outline-offset: -3px;
	}
	.free {
		display: flex;
		align-items: center;
		gap: 0.4rem;
		font-size: 0.8rem;
		color: var(--yack-gray);
	}
	.free input {
		width: 5rem;
		padding: 0.35rem 0.45rem;
		font: inherit;
		border: 1px solid var(--yack-border);
		border-radius: 0.375rem;
	}
	.free input:focus-visible {
		outline: 2px solid var(--yack-magenta);
		outline-offset: 1px;
	}
</style>
