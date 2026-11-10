<script lang="ts">
	// Espacement of a block (E4): four fields, top, right, bottom, left, for the style properties hautEspace,
	// droiteEspace, basEspace, gaucheEspace (stored as before: one attribute `yb-<property>` each, spacing.ts).
	// The padlock « lier » (on when the four sides agree, so it opens by itself on a block whose sides differ): a value
	// typed in a field, or picked in the quick menu « Pas » (the steps of the charte), goes to the four sides; unlinked,
	// to the side chosen (the field last focused). A field takes a step (0, S, M, L, XL) or, for the accounts allowed to
	// leave the charte, a number of px; at the Charte level the fields are read-only and the menu sets the value. A
	// change is one `addAttributes` (canvas re-render, one undo step with Ctrl+Z). Mockup: front/static/ui-kit/panneau.html.
	import { tick } from 'svelte';
	import type { TraitField, TraitFieldProps } from './registerTraits.js';
	import { allowsFreeValues, warnsOnFreeValues } from '#lib/editor/panels/blockStyle.js';
	import {
		commonValue,
		offCharteSides,
		parseSpacing,
		readSpacing,
		sidesAgree,
		spacingChanges,
		spacingText,
		SPACING_SIDES,
		targetSides,
		type SpacingProperty
	} from './spacing.js';
	import { ArrowDownToLine, ArrowLeftToLine, ArrowRightToLine, ArrowUpToLine, Check, ChevronDown, Link2, Link2Off } from '#lib/editor/icons.js';
	import NoticeIcon from './NoticeIcon.svelte';

	let { trait, component, me, labelId }: TraitFieldProps = $props();

	const ICONS = { hautEspace: ArrowUpToLine, droiteEspace: ArrowRightToLine, basEspace: ArrowDownToLine, gaucheEspace: ArrowLeftToLine };

	// svelte-ignore state_referenced_locally
	let values = $state(readSpacing(component.getAttributes()));
	// svelte-ignore state_referenced_locally
	let linked = $state(sidesAgree(values));
	/** Side the menu and an unlinked value go to: the field last focused. */
	let side = $state<SpacingProperty>('hautEspace');
	/** Text being typed, not applied yet (shown in the four fields while they are linked). */
	let typed = $state<{ side: SpacingProperty; text: string } | null>(null);
	let error = $state<string | null>(null);
	let menuOpen = $state(false);
	let menuButton = $state<HTMLButtonElement>();
	let menu = $state<HTMLElement>();
	let wrap = $state<HTMLElement>();

	/** The four attributes changed elsewhere (undo, another panel): read them again. */
	function sync() {
		const next = readSpacing(component.getAttributes());
		if (SPACING_SIDES.every(({ property }) => next[property] === values[property])) return;
		values = next;
		typed = null;
		error = null;
		// A linked entry would overwrite sides that now differ.
		if (linked && !sidesAgree(next)) linked = false;
	}
	$effect(() => {
		const target = component;
		target.on('change:attributes', sync);
		return () => target.off('change:attributes', sync);
	});

	export const update: TraitField['update'] = () => sync();

	const label = $derived(trait.getLabel());
	const id = $props.id();
	const free = $derived(allowsFreeValues(me.level));
	const targets = $derived(targetSides(linked, side));
	const common = $derived(commonValue(values, targets));
	const offCharte = $derived(offCharteSides(values));
	const steps = $derived(me.charte.spacings);
	const targetName = $derived(linked ? 'les quatre côtés' : (SPACING_SIDES.find(({ property }) => property === side)?.where ?? ''));

	function apply(value: string, sides: readonly SpacingProperty[]) {
		typed = null;
		error = null;
		const changes = spacingChanges(values, sides, value);
		if (changes === null) return;
		const next = { ...values };
		for (const property of sides) next[property] = value;
		values = next;
		component.addAttributes(changes);
	}

	function commit(property: SpacingProperty, input: string) {
		if (!free) return;
		const result = parseSpacing(input, me.charte, free);
		if (!result.ok) {
			error = result.message;
			return;
		}
		apply(result.value, targetSides(linked, property));
	}

	function shownText(property: SpacingProperty): string {
		if (typed && (linked || typed.side === property)) return typed.text;
		return spacingText(values[property]);
	}

	async function openMenu() {
		menuOpen = true;
		await tick();
		const items = [...(menu?.querySelectorAll<HTMLElement>('[role="menuitemradio"]') ?? [])];
		(items.find((item) => item.getAttribute('aria-checked') === 'true') ?? items[0])?.focus();
	}

	function closeMenu(focus: boolean) {
		menuOpen = false;
		if (focus) menuButton?.focus();
	}

	function pickStep(value: string) {
		apply(value, targets);
		closeMenu(true);
	}

	function menuKeydown(event: KeyboardEvent) {
		const items = [...(menu?.querySelectorAll<HTMLElement>('[role="menuitemradio"]') ?? [])];
		const index = items.indexOf(document.activeElement as HTMLElement);
		const move: Record<string, number> = { ArrowDown: index + 1, ArrowUp: index - 1, Home: 0, End: items.length - 1 };
		if (event.key === 'Escape') {
			event.preventDefault();
			event.stopPropagation();
			closeMenu(true);
		} else if (event.key in move) {
			event.preventDefault();
			items[(move[event.key] + items.length) % items.length]?.focus();
		}
	}

	/** The menu closes when the focus or a click leaves it. */
	$effect(() => {
		if (!menuOpen) return;
		const outside = (event: PointerEvent) => {
			if (!wrap?.contains(event.target as Node)) closeMenu(false);
		};
		document.addEventListener('pointerdown', outside);
		return () => document.removeEventListener('pointerdown', outside);
	});
</script>

<div class="ui-row ui-row-top yb-spacing">
	<div class="ui-label-cell">
		<span class="ui-label" id={labelId}>{label}</span>
		<div class="ui-spacing-tools">
			<button
				type="button"
				class="ui-icon-btn"
				aria-pressed={linked}
				aria-label="Lier les quatre côtés"
				data-tip={linked ? 'Côtés liés' : 'Côtés indépendants'}
				onclick={() => (linked = !linked)}
			>
				{#if linked}<Link2 class="ui-ic" />{:else}<Link2Off class="ui-ic" />{/if}
			</button>
			<div
				class="ui-menu-wrap"
				bind:this={wrap}
				onfocusout={(event) => {
					if (menuOpen && !wrap?.contains(event.relatedTarget as Node)) closeMenu(false);
				}}
			>
				<button
					type="button"
					class="ui-menu-btn"
					bind:this={menuButton}
					aria-haspopup="menu"
					aria-expanded={menuOpen}
					aria-controls="{id}-steps"
					aria-label="Pas de la charte pour {targetName}"
					data-tip="Pas de la charte"
					onclick={() => (menuOpen ? closeMenu(false) : openMenu())}
					onkeydown={(event) => {
						if (event.key === 'Escape' && menuOpen) {
							// Le menu se ferme seul : Échap ne remonte pas jusqu'au raccourci qui désélectionne le bloc (B8-5).
							event.stopPropagation();
							closeMenu(false);
						}
						if (event.key === 'ArrowDown' && !menuOpen) {
							event.preventDefault();
							openMenu();
						}
					}}>Pas<ChevronDown class="ui-ic ui-ic-sm" /></button
				>
				<ul class="ui-menu" id="{id}-steps" role="menu" aria-label="Pas de la charte" hidden={!menuOpen} bind:this={menu} onkeydown={menuKeydown}>
					<li role="none">
						<button type="button" role="menuitemradio" aria-checked={common === ''} tabindex="-1" onclick={() => pickStep('')}
							><b aria-hidden="true"></b><span class="ui-caption">Par défaut</span><Check class="ui-ic ui-ic-sm" /></button
						>
					</li>
					{#each steps as step (step.key)}
						<li role="none">
							<button type="button" role="menuitemradio" aria-checked={common === step.key} tabindex="-1" onclick={() => pickStep(step.key)}
								><b>{step.key.toUpperCase()}</b><span class="ui-caption">{step.label}, {step.value.replace(/px$/, ' px')}</span><Check class="ui-ic ui-ic-sm" /></button
							>
						</li>
					{/each}
				</ul>
			</div>
		</div>
	</div>
	<div class="ui-control ui-spacing-grid" role="group" aria-labelledby={labelId}>
		{#each SPACING_SIDES as entry (entry.property)}
			{@const Icon = ICONS[entry.property]}
			<label class="ui-field" class:is-readonly={!free} class:is-target={!linked && side === entry.property}>
				<Icon class="ui-ic ui-ic-sm" />
				<span class="ui-sr-only">{entry.label}</span>
				<input
					data-side={entry.property}
					value={shownText(entry.property)}
					readonly={!free}
					placeholder="Défaut"
					autocomplete="off"
					spellcheck="false"
					aria-describedby="{id}-help{error ? ` ${id}-error` : ''}"
					aria-invalid={error && typed?.side === entry.property ? 'true' : undefined}
					onfocus={() => (side = entry.property)}
					oninput={(event) => (typed = { side: entry.property, text: event.currentTarget.value })}
					onchange={(event) => commit(entry.property, event.currentTarget.value)}
					onkeydown={(event) => {
						if (event.key === 'Enter') commit(entry.property, event.currentTarget.value);
						if (event.key === 'Escape' && typed) {
							event.stopPropagation();
							typed = null;
							error = null;
						}
					}}
				/>
			</label>
		{/each}
		<span class="ui-sr-only" id="{id}-help"
			>{free
				? 'Un pas de la charte (0, S, M, L, XL) ou un nombre de px de 0 à 200. Vide : réglage par défaut.'
				: 'Votre niveau (Charte) ne permet pas de valeur libre : choisissez un pas dans le menu « Pas ».'}{linked ? ' Côtés liés : la valeur va aux quatre côtés.' : ''}</span
		>
	</div>
	{#if error}<small class="ui-error ui-full" id="{id}-error" role="alert">{error}</small>{/if}

	{#if offCharte.length > 0}
		{#if !free}
			<p class="ui-note ui-full" role="status">
				<NoticeIcon /><span>Valeur actuelle hors charte : {offCharte.map((entry) => `${values[entry.property]} ${entry.where}`).join(', ')}. Votre compte ne permet pas d'en poser : choisissez un pas de la charte pour la remplacer, ou laissez-la.</span>
			</p>
		{:else if warnsOnFreeValues(me.level)}
			<p class="ui-banner ui-full" role="status"><NoticeIcon kind="warning" /><span>Hors charte : cette valeur n'est pas dans la charte Yack</span></p>
		{/if}
	{/if}
</div>

<style>
	/* Padlock and quick menu under the label; four fields in two columns (mockup « Espacement »). */
	.ui-spacing-tools {
		display: flex;
		gap: var(--ui-space-1);
		width: 100%;
	}
	.ui-spacing-tools > button::after {
		left: 0;
		transform: none;
	}
	.ui-spacing-grid {
		/* Width of the row, not that of four default inputs. */
		flex: 1 1 0;
		min-width: 0;
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: var(--ui-space-1);
	}
	.ui-field input {
		font-variant-numeric: tabular-nums;
	}
	.ui-field.is-readonly {
		background: var(--ui-bg-subtle);
	}
	.ui-field.is-readonly input {
		cursor: default;
	}
	/* Unlinked: the side the menu and the next value go to. */
	.ui-field.is-target {
		border-color: var(--ui-accent);
	}
	.ui-menu-wrap {
		position: relative;
	}
	.ui-menu-btn {
		display: inline-flex;
		align-items: center;
		gap: calc(var(--ui-space-1) / 2);
		height: var(--ui-control);
		padding: 0 var(--ui-space-1) 0 var(--ui-space-2);
		font-size: var(--ui-text-sm);
		font-weight: var(--ui-weight-bold);
		color: var(--ui-text);
		background: var(--ui-bg);
		border: var(--ui-border-width) solid var(--ui-border-control);
		border-radius: var(--ui-radius);
		cursor: pointer;
	}
	.ui-menu-btn:hover {
		border-color: var(--ui-border-hover);
	}
	.ui-menu-btn[aria-expanded='true'] {
		background: var(--ui-bg-subtle);
		border-color: var(--ui-accent);
	}
	.ui-menu-btn:focus-visible {
		outline: var(--ui-focus-width) solid var(--ui-accent);
		outline-offset: var(--ui-border-width);
	}
	.ui-menu {
		position: absolute;
		z-index: 5;
		top: calc(100% + var(--ui-space-1));
		left: 0;
		min-width: calc(var(--ui-control) * 6);
		margin: 0;
		padding: var(--ui-space-1) 0;
		list-style: none;
		background: var(--ui-bg);
		border: var(--ui-border-width) solid var(--ui-border-control);
		border-radius: var(--ui-radius);
		box-shadow: 0 var(--ui-space-1) var(--ui-space-3) var(--ui-shadow);
	}
	.ui-menu button {
		display: flex;
		align-items: center;
		gap: var(--ui-space-2);
		width: 100%;
		height: var(--ui-control);
		padding: 0 var(--ui-space-2);
		font-size: var(--ui-text-sm);
		color: var(--ui-text);
		text-align: left;
		background: none;
		border: 0;
		cursor: pointer;
	}
	.ui-menu button:hover {
		background: var(--ui-bg-subtle);
	}
	.ui-menu button:focus-visible {
		outline: var(--ui-focus-width) solid var(--ui-accent);
		outline-offset: calc(-1 * var(--ui-focus-width));
	}
	.ui-menu b {
		width: var(--ui-swatch);
	}
	.ui-menu .ui-caption {
		flex: 1;
		white-space: nowrap;
	}
	.ui-menu button :global(.ui-ic) {
		visibility: hidden;
		color: var(--ui-accent);
	}
	.ui-menu button[aria-checked='true'] :global(.ui-ic) {
		visibility: visible;
	}
</style>
