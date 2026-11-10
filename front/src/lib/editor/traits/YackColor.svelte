<script lang="ts">
	// Colour of a block (Fond, Texte, Accent; D9): the swatches of the charte, plus a free colour
	// (#rrggbb) for the accounts allowed to leave the charte (BuilderMe.level). A free value equal to a
	// colour of the charte becomes its key, as Sylius does: the swatch shows at once. « Par défaut » (no
	// value) is the button « revenir au réglage par défaut » next to the label, shown once a colour is set
	// (mockup front/static/ui-kit/panneau.html); the hex field sits under the label.
	import type { TraitField, TraitFieldProps } from './registerTraits.js';
	import { allowsFreeValues, normalizeFreeValue, warnsOnFreeValues } from '#lib/editor/panels/blockStyle.js';
	import { rovingKeydown } from '#lib/editor/panels/roving.js';
	import { RotateCcw } from '#lib/editor/icons.js';
	import NoticeIcon from './NoticeIcon.svelte';

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
	const colors = $derived(me.charte.colors);
	const free = $derived(allowsFreeValues(me.level));
	const current = $derived(colors.find((color) => color.key === value) ?? null);
	/** Value outside the charte (set by a Libre account, or typed here). */
	const offCharte = $derived(value !== '' && current === null);
	const selectedIndex = $derived(Math.max(0, colors.findIndex((swatch) => swatch.key === value)));
	const tabStop = $derived(focusIndex ?? selectedIndex);
	/** The hex field shows the colour in use: the free value, or the code of the swatch picked. */
	const shownHex = $derived(offCharte ? value : (current?.value ?? ''));
	const id = $props.id();

	function pick(key: string) {
		error = null;
		typed = null;
		if (key === value) return;
		value = key;
		set(key);
	}

	function applyFree(input: string) {
		// The field shows the colour in use: emptying it goes back to the default.
		if (input.trim() === '') return pick('');
		const result = normalizeFreeValue('color', input, me.charte);
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

<div class="ui-row ui-row-top yb-color">
	<div class="ui-label-cell">
		<span class="ui-label" id={labelId}>{label}</span>
		{#if value !== ''}
			<button type="button" class="ui-reset" aria-label="{label} : revenir au réglage par défaut" data-tip="Par défaut" onclick={() => pick('')}
				><RotateCcw class="ui-ic" /></button
			>
		{/if}
		{#if free}
			<input
				class="ui-input ui-input-num"
				type="text"
				maxlength="7"
				placeholder="Par défaut"
				spellcheck="false"
				autocomplete="off"
				aria-label="{label} : couleur libre, code hexadécimal"
				aria-invalid={error ? 'true' : undefined}
				aria-describedby={error ? `${id}-error` : undefined}
				value={typed ?? shownHex}
				oninput={(event) => (typed = event.currentTarget.value)}
				onchange={(event) => applyFree(event.currentTarget.value)}
				onkeydown={(event) => {
					if (event.key === 'Enter') applyFree(event.currentTarget.value);
				}}
			/>
		{/if}
	</div>
	<div
		class="ui-control ui-swatches"
		role="toolbar"
		aria-labelledby={labelId}
		tabindex="-1"
		onkeydown={(event) => {
			const next = rovingKeydown(event, tabStop);
			if (next !== null) focusIndex = next;
		}}
		onfocusout={() => (focusIndex = null)}
	>
		{#each colors as swatch, index (swatch.key)}
			<button
				type="button"
				data-roving
				class="ui-swatch"
				style:background-color={swatch.value}
				aria-pressed={swatch.key === value}
				aria-label={swatch.label}
				data-tip={swatch.label}
				tabindex={index === tabStop ? 0 : -1}
				onclick={() => pick(swatch.key)}
			></button>
		{/each}
	</div>
	{#if error}<small class="ui-error ui-full" id="{id}-error" role="alert">{error}</small>{/if}

	{#if offCharte}
		{#if !free}
			<p class="ui-note ui-full" role="status">
				<NoticeIcon /><span>Valeur actuelle hors charte : {value}. Votre compte ne permet pas d'en poser : choisissez une couleur de la charte pour la remplacer, ou laissez-la.</span>
			</p>
		{:else if warnsOnFreeValues(me.level)}
			<p class="ui-banner ui-full" role="status"><NoticeIcon kind="warning" /><span>Hors charte : cette couleur n'est pas dans la charte Yack</span></p>
		{/if}
	{/if}
</div>
