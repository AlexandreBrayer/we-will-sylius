<script lang="ts">
	// Panel of the selected block (D10): tabs Contenu (props), Style (style of the block), Avancé
	// (profil ciblé, masquer, ancre). Each trait of the selection is rendered by its GrapesJS trait view
	// (types of #lib/editor/traits/registerTraits.ts) in the container of its tab (`tab` of the registry).
	import { onDestroy } from 'svelte';
	import type { Component, Editor, Trait } from 'grapesjs';
	import type { TraitTab } from '#lib/blocks/registry.js';
	import { releaseTraitFields, traitSetting } from '#lib/editor/traits/registerTraits.js';
	import { rovingKeydown } from './roving.js';

	let { editor }: { editor: Editor | null } = $props();

	const TABS: { id: TraitTab; label: string; empty: string }[] = [
		{ id: 'contenu', label: 'Contenu', empty: 'Ce bloc lit son contenu sur la fiche : rien à régler ici.' },
		{ id: 'style', label: 'Style', empty: '' },
		{ id: 'avance', label: 'Avancé', empty: '' }
	];

	let tab = $state<TraitTab>('contenu');
	let selected = $state<Component | null>(null);
	let counts = $state<Record<TraitTab, number>>({ contenu: 0, style: 0, avance: 0 });
	const containers: Partial<Record<TraitTab, HTMLDivElement>> = {};
	let views: { remove(): unknown }[] = [];

	type TraitViewClass = new (options: Record<string, unknown>) => { render(): { el: HTMLElement }; remove(): unknown };

	function clear() {
		for (const view of views) view.remove();
		views = [];
		for (const container of Object.values(containers)) {
			if (!container) continue;
			releaseTraitFields(container);
			container.replaceChildren();
		}
	}

	function render(ed: Editor) {
		clear();
		const component = ed.getSelected() ?? null;
		selected = component;
		const next: Record<TraitTab, number> = { contenu: 0, style: 0, avance: 0 };
		if (component) {
			const config = ed.Traits.getConfig();
			for (const trait of component.getTraits() as Trait[]) {
				const id = traitSetting<TraitTab>(trait, 'tab') ?? 'contenu';
				const container = containers[id];
				if (!container) continue;
				const View = (ed.Traits.getType(trait.getType()) ?? ed.Traits.getType('text')) as unknown as TraitViewClass;
				const view = new View({ config, model: trait, attributes: traitSetting(trait, 'attributes') });
				container.appendChild(view.render().el);
				views.push(view);
				next[id]++;
			}
		}
		counts = next;
	}

	$effect(() => {
		const ed = editor;
		if (!ed) return;
		let queued = false;
		// After GrapesJS's own selection handlers (the traits get their target first).
		const onToggled = () => {
			if (queued) return;
			queued = true;
			queueMicrotask(() => {
				queued = false;
				render(ed);
			});
		};
		ed.on('component:toggled', onToggled);
		ed.on('component:remove', onToggled);
		render(ed);
		return () => {
			ed.off('component:toggled', onToggled);
			ed.off('component:remove', onToggled);
			clear();
		};
	});

	onDestroy(clear);

	const tabIndex = $derived(TABS.findIndex((entry) => entry.id === tab));
</script>

<div class="block-panel">
	{#if selected}
		<h2 class="name">{selected.getName()}</h2>
	{:else}
		<p class="hint">Sélectionnez un bloc dans la page ou dans l'onglet « Structure » pour régler son contenu et son style.</p>
	{/if}
	<div
		class="subtabs"
		role="tablist"
		aria-label="Réglages du bloc"
		tabindex="-1"
		hidden={!selected}
		onkeydown={(event) => {
			const next = rovingKeydown(event, tabIndex);
			if (next !== null) tab = TABS[next]?.id ?? tab;
		}}
	>
		{#each TABS as entry (entry.id)}
			<button
				type="button"
				role="tab"
				data-roving
				id="block-tab-{entry.id}"
				aria-controls="block-panel-{entry.id}"
				aria-selected={tab === entry.id}
				tabindex={tab === entry.id ? 0 : -1}
				onclick={() => (tab = entry.id)}>{entry.label}</button
			>
		{/each}
	</div>
	{#each TABS as entry (entry.id)}
		<div id="block-panel-{entry.id}" role="tabpanel" aria-labelledby="block-tab-{entry.id}" hidden={!selected || tab !== entry.id} data-test-block-tab={entry.id}>
			{#if selected && counts[entry.id] === 0 && entry.empty}<p class="hint">{entry.empty}</p>{/if}
			<div class="traits" bind:this={containers[entry.id]}></div>
		</div>
	{/each}
</div>

<style>
	.name {
		margin: 0 0 0.6rem;
	}
	.subtabs {
		display: flex;
		margin: 0 0 1rem;
		border: 1px solid var(--yack-border);
		border-radius: 0.375rem;
		overflow: hidden;
	}
	.subtabs[hidden] {
		display: none;
	}
	.subtabs button {
		flex: 1;
		padding: 0.45rem 0.5rem;
		font: inherit;
		font-size: 0.8rem;
		font-weight: 600;
		color: var(--yack-gray);
		background: transparent;
		border: 0;
		border-right: 1px solid var(--yack-border);
		cursor: pointer;
	}
	.subtabs button:last-child {
		border-right: 0;
	}
	.subtabs button[aria-selected='true'] {
		color: var(--yack-magenta-dark);
		background: color-mix(in srgb, var(--yack-magenta) 14%, transparent);
		box-shadow: inset 0 -3px 0 var(--yack-magenta);
	}
	.subtabs button:focus-visible {
		outline: 2px solid var(--yack-dark);
		outline-offset: -3px;
	}

	/* GrapesJS trait views (default types and Yack fields). */
	.traits :global(.gjs-trt-trait) {
		display: flex;
		flex-direction: column;
		align-items: stretch;
		padding: 0 0 0.9rem;
	}
	.traits :global(.gjs-label-wrp) {
		width: auto;
		min-width: 0;
		padding-bottom: 0.3rem;
	}
	.traits :global(.gjs-label),
	.traits :global(.yb-trait-label) {
		font-size: 0.8rem;
		font-weight: 600;
		color: var(--yack-dark);
	}
	.traits :global(.gjs-field) {
		border: 1px solid var(--yack-border);
		border-radius: 0.375rem;
		color: var(--yack-dark);
	}
	.traits :global(.gjs-field-yack-color),
	.traits :global(.gjs-field-yack-choice),
	.traits :global(.gjs-field-yack-anchor) {
		border: 0;
		background: none;
	}
	.traits :global(.gjs-field input),
	.traits :global(.gjs-field textarea),
	.traits :global(.gjs-field select) {
		padding: 0.45rem 0.55rem;
		font: inherit;
		font-size: 0.85rem;
		color: var(--yack-dark);
		width: 100%;
		background: transparent;
		border: 0;
		box-sizing: border-box;
	}
	.traits :global(.gjs-field-yack-anchor input) {
		border: 1px solid var(--yack-border);
		border-radius: 0.375rem;
	}
	.traits :global(.gjs-field input:focus-visible),
	.traits :global(.gjs-field textarea:focus-visible),
	.traits :global(.gjs-field select:focus-visible) {
		outline: 2px solid var(--yack-magenta);
		outline-offset: 1px;
	}
	.traits :global(.yb-trait-help) {
		display: block;
		margin-top: 0.3rem;
		font-size: 0.75rem;
		color: var(--yack-gray);
	}
	.traits :global(.yb-trait-error) {
		display: block;
		margin-top: 0.3rem;
		font-size: 0.75rem;
		font-weight: 600;
		color: var(--yack-magenta-dark);
	}
	.traits :global(.yb-trait-error:empty) {
		display: none;
	}
	.traits :global(.yb-trait-warning),
	.traits :global(.yb-trait-note) {
		margin: 0;
		padding: 0.45rem 0.6rem;
		font-size: 0.78rem;
		border-radius: 0.375rem;
		border-left: 3px solid var(--yack-magenta);
		background: color-mix(in srgb, var(--yack-magenta) 10%, transparent);
		color: var(--yack-dark);
	}
	.traits :global(.yb-trait-warning) {
		font-weight: 600;
	}
</style>
