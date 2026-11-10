<script lang="ts">
	// « Mes sections » in the palette of the builder (D12): the sections saved by the administrators, by
	// name. A line is dragged into the page or clicked (added at the end): its blocks are copied, with their
	// style, no link to the section (panels/sectionCanvas.ts). A Charte account is warned before inserting a
	// section that holds a value outside the charte: Sylius would refuse to save the page with it. Each
	// section is renamed or deleted from its line, after a confirmation; deleting changes no page.
	// Last rubrique of the palette (E6), folded when the builder opens; the search of the palette
	// (#lib/editor/palette.ts) filters the sections by name and unfolds the rubrique when one matches.
	import { tick } from 'svelte';
	import type { Editor } from 'grapesjs';
	import { deleteSection, getSections, renameSection } from '#lib/remote/builder.remote.js';
	import { insertSection, startSectionDrag } from '#lib/editor/panels/sectionCanvas.js';
	import { matchesSearch, normalizeSearch } from '#lib/editor/palette.js';
	import { ChevronDown } from '#lib/editor/icons.js';
	import {
		cleanSectionName,
		deleteQuestion,
		holdsFreeValues,
		insertedMessage,
		insertWarning,
		SECTION_NAME_MAX,
		sectionDetail,
		sectionNameIssue,
		type Section
	} from '#lib/editor/panels/sections.js';

	let {
		editor,
		level,
		query = '',
		matches = $bindable(0),
		onmessage
	}: {
		editor: Editor | null;
		/** Level of the account (`charte`: warned before inserting a section outside the charte). */
		level: string;
		/** Search of the palette: only the sections whose name matches, rubrique unfolded (hidden when none does). */
		query?: string;
		/** Sections matching the search (0 without a search): the palette says « Aucun bloc » only when none does. */
		matches?: number;
		/** Message of the toolbar of the builder (insertion, renaming, deletion). */
		onmessage: (message: { kind: 'ok' | 'error'; text: string }) => void;
	} = $props();

	const sections = getSections();
	const all = $derived(sections.current ?? []);
	const searching = $derived(normalizeSearch(query) !== '');
	const items = $derived(searching ? all.filter((section) => matchesSearch(section.name, query)) : all);
	$effect.pre(() => {
		matches = searching ? items.length : 0;
	});
	/** Folded when the builder opens; a search unfolds it, unless folded again during this very search (`closedFor`). */
	let opened = $state(false);
	let closedFor = $state<string | null>(null);
	const expanded = $derived(searching ? closedFor !== query : opened);

	function toggle() {
		if (searching) closedFor = closedFor === query ? null : query;
		else opened = !opened;
	}
	const loadError = $derived(sections.error ? (sections.error.message ?? 'Vos sections n’ont pas pu être lues.') : null);

	/** Line asking something: its new name, the deletion, or the insertion despite the charte (Charte account). */
	let action = $state<{ id: number; kind: 'rename' | 'delete' | 'insert'; name: string; error: string | null; busy: boolean } | null>(null);
	let list = $state<HTMLUListElement>();
	let endDrag: ((event: DragEvent) => void) | null = null;

	/** French message of a failed call (expired session: back to the login form). */
	function failureText(e: unknown, fallback: string): string {
		const failure = e as { status?: number; body?: { message?: string }; message?: string };
		if (failure.status === 401) window.location.href = `/cms-admin/login?redirect=${encodeURIComponent(location.pathname)}`;
		if (failure.status === 404) return 'Cette section n’existe plus : la liste est mise à jour.';
		return failure.body?.message ?? failure.message ?? fallback;
	}

	async function focusLine(id: number, part: 'insert' | 'rename' | 'delete' | 'confirm' | 'name' = 'insert') {
		await tick();
		list?.querySelector<HTMLElement>(`[data-section="${id}"] [data-part="${part}"]`)?.focus();
	}

	async function ask(section: Section, kind: 'rename' | 'delete' | 'insert') {
		action = { id: section.id, kind, name: section.name, error: null, busy: false };
		await focusLine(section.id, kind === 'rename' ? 'name' : 'confirm');
	}

	function cancel() {
		const id = action?.id;
		const kind = action?.kind;
		action = null;
		if (id !== undefined) void focusLine(id, kind === 'insert' ? 'insert' : (kind ?? 'insert'));
	}

	/** Click (or Entrée) on a line: a copy of its blocks at the end of the page, asked twice to a Charte account outside the charte. */
	function insert(section: Section, confirmed = false) {
		if (!editor) return;
		if (!confirmed && insertWarning(section, level)) {
			void ask(section, 'insert');
			return;
		}
		action = null;
		insertSection(editor, section.blocks);
		onmessage(insertedMessage(section, level));
	}

	function dragstart(event: DragEvent, section: Section) {
		if (!editor) {
			event.preventDefault();
			return;
		}
		action = null;
		endDrag = startSectionDrag(editor, section.id, section.blocks, event, () => onmessage(insertedMessage(section, level)));
	}

	function dragend(event: DragEvent) {
		endDrag?.(event);
		endDrag = null;
	}

	async function rename(section: Section, event: SubmitEvent) {
		event.preventDefault();
		if (!action || action.busy) return;
		const name = cleanSectionName(action.name);
		action.error = sectionNameIssue(name);
		if (action.error) return;
		if (name === section.name) {
			cancel();
			return;
		}
		action.busy = true;
		try {
			await renameSection({ id: section.id, name });
			await sections.refresh();
			action = null;
			onmessage({ kind: 'ok', text: `Section renommée : « ${name} ».` });
			await focusLine(section.id, 'rename');
		} catch (e) {
			const text = failureText(e, 'La section n’a pas pu être renommée.');
			if (action) action = { ...action, error: text, busy: false };
			if ((e as { status?: number }).status === 404) void sections.refresh();
		}
	}

	async function remove(section: Section) {
		if (!action || action.busy) return;
		action.busy = true;
		try {
			await deleteSection(section.id);
			action = null;
			await sections.refresh();
			onmessage({ kind: 'ok', text: `Section « ${section.name} » supprimée. Les pages où elle a été insérée ne changent pas.` });
			await tick();
			list?.querySelector<HTMLElement>('[data-part="insert"]')?.focus();
		} catch (e) {
			const text = failureText(e, 'La section n’a pas pu être supprimée.');
			if (action) action = { ...action, error: text, busy: false };
			if ((e as { status?: number }).status === 404) void sections.refresh();
		}
	}

	function onkeydown(event: KeyboardEvent) {
		if (event.key === 'Escape' && action) {
			event.preventDefault();
			event.stopPropagation();
			cancel();
		}
	}
</script>

<!-- svelte-ignore a11y_no_noninteractive_element_interactions -->
<section class="ui-cat sections" aria-labelledby="sections-title" hidden={searching && items.length === 0} {onkeydown}>
	<h3>
		<button type="button" id="sections-title" class="ui-cat-head" aria-expanded={expanded} aria-controls="sections-body" onclick={toggle} data-test-palette-group="Mes sections"
			><ChevronDown class="ui-ic ui-ic-sm" />Mes sections{#if items.length > 0}<span class="ui-count" aria-hidden="true">{items.length}</span>{/if}</button
		>
	</h3>
	<div id="sections-body" class="body" hidden={!expanded}>
		{#if loadError}
			<p class="ui-error" role="alert">{loadError} <button type="button" class="link" onclick={() => sections.refresh()}>Réessayer</button></p>
		{:else if sections.loading && items.length === 0}
			<p class="ui-help" role="status">Chargement de vos sections…</p>
		{:else if items.length === 0}
			<p class="ui-help">Aucune section pour l’instant : sélectionnez un bloc dans la page, puis « Enregistrer comme section » dans l’onglet Bloc.</p>
		{:else}
			<p class="ui-help">Glissez une section dans la page ou cliquez dessus (ajout en fin de page) : ses blocs sont copiés, sans lien avec la section.</p>
			<ul bind:this={list} aria-label="Mes sections">
				{#each items as section (section.id)}
					{@const free = holdsFreeValues(section.blocks)}
					{@const current = action?.id === section.id ? action : null}
					<li data-section={section.id}>
						<button
							type="button"
							class="insert"
							draggable={editor !== null}
							data-part="insert"
							aria-describedby="section-{section.id}-detail"
							disabled={!editor}
							onclick={() => insert(section)}
							ondragstart={(event) => dragstart(event, section)}
							ondragend={dragend}
						>
							<strong>{section.name}</strong>
							<small id="section-{section.id}-detail">
								{sectionDetail(section)}{#if free}{' · '}<span class="free">hors charte</span>{/if}
							</small>
						</button>
						<div class="actions">
							<button type="button" class="link" data-part="rename" aria-label="Renommer la section « {section.name} »" onclick={() => ask(section, 'rename')}>Renommer</button>
							<button type="button" class="link" data-part="delete" aria-label="Supprimer la section « {section.name} »" onclick={() => ask(section, 'delete')}>Supprimer</button>
						</div>
						{#if current?.kind === 'rename'}
							<form class="ask" onsubmit={(event) => rename(section, event)} novalidate>
								<label for="section-{section.id}-name">Nouveau nom</label>
								<input
									class="ui-input"
									id="section-{section.id}-name"
									data-part="name"
									bind:value={current.name}
									maxlength={SECTION_NAME_MAX}
									autocomplete="off"
									aria-invalid={current.error ? 'true' : undefined}
									aria-describedby={current.error ? `section-${section.id}-error` : undefined}
								/>
								{#if current.error}<p id="section-{section.id}-error" class="ui-error" role="alert">{current.error}</p>{/if}
								<div class="ui-actions buttons">
									<button type="submit" class="ui-btn" disabled={current.busy}>{current.busy ? 'Enregistrement…' : 'Renommer'}</button>
									<button type="button" class="ui-btn" onclick={cancel}>Annuler</button>
								</div>
							</form>
						{:else if current?.kind === 'delete'}
							<div class="ask" role="group" aria-labelledby="section-{section.id}-question">
								<p id="section-{section.id}-question">{deleteQuestion(section.name)}</p>
								{#if current.error}<p class="ui-error" role="alert">{current.error}</p>{/if}
								<div class="ui-actions buttons">
									<button type="button" class="ui-btn" data-part="confirm" disabled={current.busy} onclick={() => remove(section)}>{current.busy ? 'Suppression…' : 'Oui, supprimer'}</button>
									<button type="button" class="ui-btn" onclick={cancel}>Annuler</button>
								</div>
							</div>
						{:else if current?.kind === 'insert'}
							<div class="ask warning" role="group" aria-labelledby="section-{section.id}-warning">
								<p id="section-{section.id}-warning">{insertWarning(section, level)}</p>
								<div class="ui-actions buttons">
									<button type="button" class="ui-btn" data-part="confirm" onclick={() => insert(section, true)}>Insérer quand même</button>
									<button type="button" class="ui-btn" onclick={cancel}>Annuler</button>
								</div>
							</div>
						{/if}
					</li>
				{/each}
			</ul>
		{/if}
	</div>
</section>

<style>
	h3 {
		margin: 0;
	}
	.body {
		display: grid;
		gap: var(--ui-space-2);
		padding: 0 var(--ui-space-3) var(--ui-space-3);
	}
	.body > p {
		margin: 0;
	}
	ul {
		display: grid;
		gap: var(--ui-space-1);
		margin: 0;
		padding: 0;
		list-style: none;
	}
	li {
		border: var(--ui-border-width) solid var(--ui-border);
		border-radius: var(--ui-radius);
		overflow: hidden;
	}
	li:hover,
	li:focus-within {
		border-color: var(--ui-border-hover);
	}
	.insert {
		display: flex;
		flex-direction: column;
		gap: 0;
		width: 100%;
		padding: var(--ui-space-1) var(--ui-space-2) 0;
		text-align: left;
		font-size: var(--ui-text-sm);
		color: var(--ui-text);
		background: transparent;
		border: 0;
		cursor: grab;
	}
	.insert:hover {
		background: var(--ui-bg-subtle);
	}
	.insert:disabled {
		cursor: default;
	}
	.insert small {
		font-size: var(--ui-text-xs);
		color: var(--ui-text-label);
	}
	.free {
		font-weight: var(--ui-weight-bold);
		color: var(--ui-text);
	}
	.actions {
		display: flex;
		gap: var(--ui-space-3);
		padding: 0 var(--ui-space-2) var(--ui-space-1);
	}
	.link {
		padding: 0;
		font-size: var(--ui-text-xs);
		color: var(--ui-text-label);
		background: none;
		border: 0;
		text-decoration: underline;
		cursor: pointer;
	}
	.link:hover {
		color: var(--ui-text);
	}
	.insert:focus-visible {
		outline: var(--ui-focus-width) solid var(--ui-accent);
		outline-offset: calc(-1 * var(--ui-focus-width));
	}
	.link:focus-visible {
		outline: var(--ui-focus-width) solid var(--ui-accent);
		outline-offset: var(--ui-focus-width);
	}
	.ask {
		display: grid;
		gap: var(--ui-space-2);
		padding: var(--ui-space-2);
		font-size: var(--ui-text-sm);
		border-top: var(--ui-border-width) solid var(--ui-border);
	}
	.ask p {
		margin: 0;
	}
	.ask.warning p {
		font-weight: var(--ui-weight-bold);
	}
	.ask label {
		display: block;
		font-weight: var(--ui-weight-bold);
		color: var(--ui-text-label);
	}
</style>
