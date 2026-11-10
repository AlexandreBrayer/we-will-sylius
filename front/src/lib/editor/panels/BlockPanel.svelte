<script lang="ts">
	// Panel of the selected block (D10): tabs Contenu (props), Style (style of the block), Avancé
	// (profil ciblé, masquer, ancre). Each trait of the selection is rendered by its GrapesJS trait view
	// (types of #lib/editor/traits/registerTraits.ts) in the container of its tab (`tab` of the registry);
	// the Style tab is arranged in groups (panelLayout.ts). Look: mini-charte of the tool (ui-kit.css,
	// reference front/static/ui-kit/panneau.html). « Enregistrer comme section » sits in the head (SaveSection).
	import { onDestroy } from 'svelte';
	import type { Component, Editor, Trait } from 'grapesjs';
	import { blockDefinition, type TraitTab } from '#lib/blocks/registry.js';
	import { COLUMN_TYPE } from '#lib/editor/layouts.js';
	import { releaseTraitFields, traitSetting } from '#lib/editor/traits/registerTraits.js';
	import { arrangeStyle } from './panelLayout.js';
	import { rovingKeydown } from './roving.js';
	import SaveSection from './SaveSection.svelte';

	let {
		editor,
		level,
		onmessage
	}: {
		editor: Editor | null;
		/** Level of the account (« Enregistrer comme section »). */
		level: string;
		/** Message of the toolbar of the builder (section saved). */
		onmessage: (message: { kind: 'ok' | 'error'; text: string }) => void;
	} = $props();

	const TABS: { id: TraitTab; label: string; empty: string }[] = [
		{ id: 'contenu', label: 'Contenu', empty: 'Ce bloc lit son contenu sur la fiche : rien à régler ici.' },
		{ id: 'style', label: 'Style', empty: '' },
		{ id: 'avance', label: 'Avancé', empty: '' }
	];
	/** Contenu of a section: no props of its own, it holds blocks. */
	const SECTION_EMPTY = 'Une section regroupe des blocs : glissez-y des blocs ou des colonnes. Fond, texte, espaces et largeur : onglet Style.';
	const ZONE_HINT = 'Mise en page de la fiche, comme sur la boutique : elle suit ses blocs, rien à régler ici.';
	const COLUMN_HINT = 'Colonne : glissez-y des blocs de contenu (les blocs pleine largeur restent hors des colonnes). Répartition, alignement et espace : sélectionnez « Colonnes » au-dessus.';

	let tab = $state<TraitTab>('contenu');
	let selected = $state<Component | null>(null);
	/** Selection that is not a block: a product zone or one of its columns (template, #lib/editor/zones.ts), a column of a colonnes (layouts.ts). */
	let layout = $state<string | null>(null);
	let counts = $state<Record<TraitTab, number>>({ contenu: 0, style: 0, avance: 0 });
	const containers = $state<Partial<Record<TraitTab, HTMLDivElement>>>({});
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

	/**
	 * A field of the mini-charte (Svelte, registerTraits.ts) leaves the `gjs-field` box of GrapesJS, whose rules
	 * (no border, no focus outline) would override it. The GrapesJS label is a div, not a <label>: a default field
	 * (text, textarea, select) gets its name from it.
	 */
	function prepareField(el: HTMLElement, trait: Trait) {
		for (const box of el.querySelectorAll<HTMLElement>('.gjs-field')) {
			if ([...box.children].some((child) => child.classList.contains('yb-trait-field'))) box.classList.remove('gjs-field');
		}
		for (const field of el.querySelectorAll<HTMLElement>('.gjs-field input, .gjs-field select, .gjs-field textarea')) {
			if (!field.hasAttribute('aria-label') && !field.hasAttribute('aria-labelledby')) field.setAttribute('aria-label', trait.getLabel());
		}
	}

	/** Style tab: the fields in the groups of the mockup (Couleurs, Titre, Mise en page). */
	function appendGrouped(container: HTMLElement, rendered: { trait: Trait; el: HTMLElement }[]) {
		for (const { group, items } of arrangeStyle(rendered, ({ trait }) => traitSetting<string>(trait, 'styleProperty'))) {
			const section = document.createElement('section');
			section.className = 'ui-group';
			const title = document.createElement('h3');
			title.className = 'ui-group-title';
			title.id = `block-group-${group.id}`;
			title.textContent = group.title;
			section.setAttribute('aria-labelledby', title.id);
			section.append(title, ...items.map(({ el }) => el));
			container.appendChild(section);
		}
	}

	function render(ed: Editor) {
		clear();
		const component = ed.getSelected() ?? null;
		selected = component;
		const type = String(component?.get('type') ?? '');
		layout = component === null || blockDefinition(type) !== undefined ? null : type === COLUMN_TYPE ? COLUMN_HINT : ZONE_HINT;
		const next: Record<TraitTab, number> = { contenu: 0, style: 0, avance: 0 };
		const style: { trait: Trait; el: HTMLElement }[] = [];
		if (component) {
			const config = ed.Traits.getConfig();
			for (const trait of component.getTraits() as Trait[]) {
				const id = traitSetting<TraitTab>(trait, 'tab') ?? 'contenu';
				const container = containers[id];
				if (!container) continue;
				const View = (ed.Traits.getType(trait.getType()) ?? ed.Traits.getType('text')) as unknown as TraitViewClass;
				const view = new View({ config, model: trait, attributes: traitSetting(trait, 'attributes') });
				const el = view.render().el;
				prepareField(el, trait);
				if (id === 'style') style.push({ trait, el });
				else container.appendChild(el);
				views.push(view);
				next[id]++;
			}
			if (containers.style) appendGrouped(containers.style, style);
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
	/** Drawing of the block in the palette (constant string of the registry), shown in the head. */
	const icon = $derived(blockDefinition(String(selected?.get('type') ?? ''))?.icon ?? null);
</script>


<div class="block-panel ui-scope">
	{#if selected}
		<div class="ui-panel-head">
			<!-- Drawn in code (icon of the registry): no outside content. -->
			{#if icon}<span class="ui-panel-icon" aria-hidden="true">{@html icon}</span>{/if}
			<h2 class="ui-panel-title" title={selected.getName()}>{selected.getName()}</h2>
			<SaveSection {editor} {level} {onmessage} />
		</div>
		{#if layout}<p class="ui-hint">{layout}</p>{/if}
	{:else}
		<p class="ui-hint">Sélectionnez un bloc dans la page ou dans l'onglet « Structure » pour régler son contenu et son style.</p>
	{/if}
	<div
		class="ui-tabs"
		role="tablist"
		aria-label="Réglages du bloc"
		tabindex="-1"
		hidden={!selected || layout !== null}
		onkeydown={(event) => {
			const next = rovingKeydown(event, tabIndex);
			if (next !== null) tab = TABS[next]?.id ?? tab;
		}}
	>
		{#each TABS as entry (entry.id)}
			<button
				type="button"
				class="ui-tab"
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
		<div
			id="block-panel-{entry.id}"
			class="ui-body"
			role="tabpanel"
			aria-labelledby="block-tab-{entry.id}"
			hidden={!selected || layout !== null || tab !== entry.id}
			data-test-block-tab={entry.id}
		>
			{#if selected && counts[entry.id] === 0 && entry.empty}<p class="ui-hint">{selected.get('type') === 'section' && entry.id === 'contenu' ? SECTION_EMPTY : entry.empty}</p>{/if}
			<div class="ui-traits" bind:this={containers[entry.id]}></div>
		</div>
	{/each}
</div>
