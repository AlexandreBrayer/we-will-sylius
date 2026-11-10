<script lang="ts">
	// Choice among pictograms (E4): Largeur, Alignement, Alignement vertical, Arrondi (style of the block) and Position du
	// visuel (prop of the Texte + image block). One choice at a time: a radiogroup whose radios carry their name
	// (`aria-label`) and a tooltip (`data-tip`, ui-kit.css: on hover after 400 ms, at once on keyboard focus). The arrows,
	// Home and End move the focus, Enter or Space picks (the button's own click), like the other fields of the panel.
	// Choices: the trait's `options` (`icon` of #lib/blocks/registry.ts), or the radii of the charte (`charte: 'radii'`).
	// Style field: `defaultId` is lit when nothing is set (picking it stores ''); otherwise no choice is lit and
	// « revenir au réglage par défaut » next to the label (shown once a value is set) clears it. A prop (no
	// `styleProperty`) always has a value: no default, no reset. Mockup: front/static/ui-kit/panneau.html.
	import { tick, type Component } from 'svelte';
	import { traitSetting, type TraitField, type TraitFieldProps } from './registerTraits.js';
	import type { ChoiceIcon, TraitOption } from '#lib/blocks/registry.js';
	import type { StyleProperty } from '#lib/blocks/style.js';
	import type { Charte } from '#lib/editor/panels/blockStyle.js';
	import { rovingKeydown } from '#lib/editor/panels/roving.js';
	import {
		AlignCenterHorizontal,
		AlignEndHorizontal,
		AlignHorizontalSpaceAround,
		AlignStartHorizontal,
		MoveHorizontal,
		PanelLeft,
		PanelRight,
		RectangleHorizontal,
		RotateCcw,
		TextAlignCenter,
		TextAlignEnd,
		TextAlignStart
	} from '#lib/editor/icons.js';

	type Radius = Extract<ChoiceIcon, `radius-${string}`>;

	/** Lucide pictograms of the choices (closed list of icons.ts). */
	const LUCIDE: Record<Exclude<ChoiceIcon, Radius>, Component<{ class?: string }>> = {
		'text-align-start': TextAlignStart,
		'text-align-center': TextAlignCenter,
		'text-align-end': TextAlignEnd,
		'align-start-horizontal': AlignStartHorizontal,
		'align-center-horizontal': AlignCenterHorizontal,
		'align-end-horizontal': AlignEndHorizontal,
		'panel-left': PanelLeft,
		'panel-right': PanelRight,
		'align-horizontal-space-around': AlignHorizontalSpaceAround,
		'rectangle-horizontal': RectangleHorizontal,
		'move-horizontal': MoveHorizontal
	};

	/** Radii: a corner more or less rounded (drawings of the mockup; Lucide has no graded corner). */
	const RADIUS: Record<Radius, string> = {
		'radius-aucun': 'M4 20V4h16',
		'radius-petit': 'M4 20V8a4 4 0 0 1 4-4h12',
		'radius-moyen': 'M4 20v-9a7 7 0 0 1 7-7h9',
		'radius-grand': 'M4 20v-5A11 11 0 0 1 15 4h5'
	};
	const isRadius = (icon: ChoiceIcon | undefined): icon is Radius => icon !== undefined && icon in RADIUS;

	let { trait, value: initial, set, me, labelId }: TraitFieldProps = $props();

	// svelte-ignore state_referenced_locally
	let value = $state(initial);
	let focusIndex = $state<number | null>(null);

	export const update: TraitField['update'] = (next) => {
		value = next;
	};

	const label = $derived(trait.getLabel());
	const property = $derived(traitSetting<StyleProperty>(trait, 'styleProperty'));
	const defaultId = $derived(property ? (traitSetting<string>(trait, 'defaultId') ?? '') : '');
	const family = $derived(traitSetting<keyof Charte>(trait, 'charte'));
	const choices = $derived.by((): TraitOption[] => {
		if (family === 'radii') {
			return me.charte.radii.map((entry) => ({ id: entry.key, label: entry.label, title: `${entry.label} (${entry.value})`, icon: `radius-${entry.key}` as Radius }));
		}
		return traitSetting<TraitOption[]>(trait, 'options') ?? [];
	});
	/** '' shows the default choice. */
	const shown = $derived(value === '' ? defaultId : value);
	const current = $derived(choices.find((choice) => choice.id === shown) ?? null);
	const selectedIndex = $derived(Math.max(0, choices.findIndex((choice) => choice.id === shown)));
	const tabStop = $derived(focusIndex ?? selectedIndex);

	function pick(id: string) {
		const next = property && id === defaultId ? '' : id;
		if (next === value) return;
		value = next;
		set(next);
	}

	/** The reset button leaves with the value: the focus goes to the choice now in use, not to the page. */
	let group = $state<HTMLElement>();
	async function reset() {
		pick(defaultId);
		await tick();
		group?.querySelector<HTMLElement>('[data-roving][tabindex="0"]')?.focus();
	}

	const tip = (choice: TraitOption) => `${choice.title ?? choice.label}${property && choice.id === defaultId ? ' (par défaut)' : ''}`;
</script>

<div class="ui-row yb-icons">
	<div class="ui-label-cell">
		<!-- No choice lit while nothing is set: the name of the group says it to a screen reader. -->
		<span class="ui-label" id={labelId}>{label}{#if current === null}<span class="ui-sr-only">{value === '' ? ' (par défaut)' : ` (valeur actuelle : ${value})`}</span>{/if}</span>
		{#if property && value !== ''}
			<button type="button" class="ui-reset" aria-label="{label} : revenir au réglage par défaut" data-tip="Par défaut" onclick={reset}
				><RotateCcw class="ui-ic" /></button
			>
		{/if}
	</div>
	<div class="ui-control">
		<div
			class="ui-seg"
			bind:this={group}
			role="radiogroup"
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
					role="radio"
					data-roving
					data-value={choice.id}
					aria-checked={choice.id === shown}
					aria-label={choice.label}
					data-tip={tip(choice)}
					tabindex={index === tabStop ? 0 : -1}
					onclick={() => pick(choice.id)}
				>
					{#if isRadius(choice.icon)}
						<svg class="ui-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
							><path d={RADIUS[choice.icon]} /></svg
						>
					{:else if choice.icon}
						{@const Icon = LUCIDE[choice.icon]}
						<Icon class="ui-ic" />
					{:else}
						{choice.label}
					{/if}
				</button>
			{/each}
		</div>
	</div>
</div>

<style>
	/* The chosen pictogram: bordered with the accent on light grey, never filled (E2), as the segments of ui-kit.css. */
	.ui-seg > button[aria-checked='true'] {
		color: var(--ui-text);
		background: var(--ui-bg-subtle);
		box-shadow: inset 0 0 0 var(--ui-border-width) var(--ui-accent);
	}
	/* Pictograms share the width of the row. */
	.ui-seg > button {
		flex: 1 1 0;
	}
</style>
