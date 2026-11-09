<script lang="ts">
	// Colour of a block (Fond, Texte, Accent; D9): the swatches of the charte, plus a free colour
	// (#rrggbb) for the accounts allowed to leave the charte (BuilderMe.level). A free value equal to a
	// colour of the charte becomes its key, as Sylius does: the swatch shows at once.
	import type { TraitField, TraitFieldProps } from './registerTraits.js';
	import { allowsFreeValues, normalizeFreeValue, warnsOnFreeValues } from '#lib/editor/panels/blockStyle.js';
	import { rovingKeydown } from '#lib/editor/panels/roving.js';
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
	/** Swatches: « Par défaut » then the charte. */
	const swatches = $derived([{ key: '', label: 'Par défaut', value: '' }, ...colors]);
	const selectedIndex = $derived(Math.max(0, swatches.findIndex((swatch) => swatch.key === value)));
	const tabStop = $derived(focusIndex ?? selectedIndex);
	const freeHex = $derived(offCharte ? value : (current?.value ?? ''));

	function pick(key: string) {
		error = null;
		typed = null;
		if (key === value) return;
		value = key;
		set(key);
	}

	function applyFree(input: string) {
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

<div class="yb-color">
	<div class="yb-trait-label" id={labelId}>{label}{#if current}<span class="current">&nbsp;: {current.label}</span>{/if}</div>
	<div
		class="swatches"
		role="toolbar"
		aria-labelledby={labelId}
		tabindex="-1"
		onkeydown={(event) => {
			const next = rovingKeydown(event, tabStop);
			if (next !== null) focusIndex = next;
		}}
		onfocusout={() => (focusIndex = null)}
	>
		{#each swatches as swatch, index (swatch.key)}
			<button
				type="button"
				data-roving
				class="swatch"
				class:none={swatch.key === ''}
				class:selected={swatch.key === value}
				style:background-color={swatch.value || undefined}
				aria-pressed={swatch.key === value}
				aria-label={swatch.key === '' ? 'Par défaut (couleur prévue par le bloc)' : swatch.label}
				title={swatch.key === '' ? 'Par défaut' : `${swatch.label} (${swatch.value})`}
				tabindex={index === tabStop ? 0 : -1}
				onclick={() => pick(swatch.key)}
			></button>
		{/each}
	</div>

	{#if free}
		<div class="free">
			<label class="picker">
				<span class="visually-hidden">{label} : couleur libre</span>
				<input type="color" value={freeHex || undefined} onchange={(event) => applyFree(event.currentTarget.value)} />
			</label>
			<input
				class="hex"
				type="text"
				maxlength="7"
				placeholder="#rrggbb"
				aria-label="{label} : couleur libre, code hexadécimal"
				aria-invalid={error ? 'true' : undefined}
				value={typed ?? (offCharte ? value : '')}
				oninput={(event) => (typed = event.currentTarget.value)}
				onchange={(event) => applyFree(event.currentTarget.value)}
				onkeydown={(event) => {
					if (event.key === 'Enter') applyFree(event.currentTarget.value);
				}}
			/>
		</div>
	{/if}
	{#if error}<small class="yb-trait-error" role="alert">{error}</small>{/if}

	{#if offCharte}
		{#if !free}
			<p class="yb-trait-note" role="status">
				<NoticeIcon /><span>Valeur actuelle hors charte : {value}. Votre compte ne permet pas d'en poser : choisissez une couleur de la charte pour la remplacer, ou laissez-la.</span>
			</p>
		{:else if warnsOnFreeValues(me.level)}
			<p class="yb-trait-warning" role="status"><NoticeIcon /><span>Hors charte : cette couleur n'est pas dans la charte Yack</span></p>
		{/if}
	{/if}
</div>

<style>
	.yb-color {
		display: grid;
		gap: 0.45rem;
	}
	.current {
		font-weight: 400;
		color: var(--yack-gray);
	}
	.swatches {
		display: flex;
		flex-wrap: wrap;
		gap: 0.4rem;
		padding: 0.2rem;
	}
	.swatch {
		width: 1.75rem;
		height: 1.75rem;
		padding: 0;
		border: 1px solid var(--yack-border);
		border-radius: var(--yack-radius-circle);
		cursor: pointer;
	}
	.swatch.none {
		background: linear-gradient(135deg, transparent 45%, var(--yack-gray) 45% 55%, transparent 55%);
	}
	.swatch.selected {
		outline: 2px solid var(--yack-magenta);
		outline-offset: 2px;
		transform: scale(1.12);
	}
	.swatch:focus-visible {
		outline: 3px solid var(--yack-dark);
		outline-offset: 2px;
	}
	.free {
		display: flex;
		gap: 0.4rem;
		align-items: center;
	}
	.picker input {
		width: 2.25rem;
		height: 2rem;
		padding: 0;
		border: 1px solid var(--yack-border);
		border-radius: var(--yack-radius-pill);
		background: none;
		cursor: pointer;
	}
	.picker input:focus-visible,
	.hex:focus-visible {
		outline: 2px solid var(--yack-magenta);
		outline-offset: 1px;
	}
	.hex {
		flex: 1;
		min-width: 0;
		padding: 0.4rem 0.5rem;
		font: inherit;
		font-size: 0.85rem;
		font-family: ui-monospace, monospace;
		border: 1px solid var(--yack-border);
		border-radius: var(--yack-radius-pill);
	}
	.visually-hidden {
		position: absolute;
		width: 1px;
		height: 1px;
		overflow: hidden;
		clip-path: inset(50%);
		white-space: nowrap;
	}
</style>
