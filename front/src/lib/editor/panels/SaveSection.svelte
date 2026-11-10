<script lang="ts">
	// « Enregistrer comme section » (D12), an icon button in the head of the panel of the selected block (its
	// nodes are items of that head: the form and the reason of a refusal take the whole width under it): asks a name, then saves the
	// selected blocks (one, or several selected with Maj) with their content and style; the section appears in
	// « Mes sections » of the palette, for every administrator. What Sylius would refuse is said before:
	// a block bound to the template, or, for a Charte account, a value outside the charte (a section is new
	// content, CharteGuard). Sylius stays the judge: its refusal is shown under the name.
	import { tick } from 'svelte';
	import type { Editor } from 'grapesjs';
	import { createSection, getSections } from '#lib/remote/builder.remote.js';
	import { selectedBlocks, type SelectedBlocks } from '#lib/editor/panels/sectionCanvas.js';
	import { cleanSectionName, saveIssue, savedMessage, SECTION_NAME_MAX, sectionNameIssue } from '#lib/editor/panels/sections.js';
	import { BookmarkPlus } from '#lib/editor/icons.js';

	let {
		editor,
		level,
		onmessage
	}: {
		editor: Editor | null;
		level: string;
		/** Message of the toolbar of the builder (section saved). */
		onmessage: (message: { kind: 'ok' | 'error'; text: string }) => void;
	} = $props();

	let selection = $state<SelectedBlocks>({ blocks: [], bound: [] });
	let open = $state(false);
	let name = $state('');
	let error = $state<string | null>(null);
	let busy = $state(false);
	let input = $state<HTMLInputElement>();
	let toggle = $state<HTMLButtonElement>();

	const count = $derived(selection.blocks.length + selection.bound.length);
	const issue = $derived(count === 0 ? null : saveIssue(selection.blocks, selection.bound, level));

	$effect(() => {
		const ed = editor;
		if (!ed) return;
		let queued = false;
		// After GrapesJS's own handlers; style changes count too (a value brought back into the charte).
		const update = () => {
			if (queued) return;
			queued = true;
			queueMicrotask(() => {
				queued = false;
				selection = selectedBlocks(ed);
				if (selection.blocks.length + selection.bound.length === 0) close();
			});
		};
		ed.on('component:toggled', update);
		ed.on('component:remove', update);
		ed.on('component:update:attributes', update);
		update();
		return () => {
			ed.off('component:toggled', update);
			ed.off('component:remove', update);
			ed.off('component:update:attributes', update);
		};
	});

	function close() {
		open = false;
		name = '';
		error = null;
	}

	async function start() {
		if (open) return cancel();
		open = true;
		error = null;
		await tick();
		input?.focus();
	}

	async function cancel() {
		close();
		await tick();
		toggle?.focus();
	}

	async function submit(event: SubmitEvent) {
		event.preventDefault();
		if (busy) return;
		const clean = cleanSectionName(name);
		error = sectionNameIssue(clean) ?? saveIssue(selection.blocks, selection.bound, level);
		if (error) return;
		busy = true;
		try {
			const saved = await createSection({ name: clean, blocks: $state.snapshot(selection.blocks) });
			void getSections().refresh();
			onmessage({ kind: 'ok', text: savedMessage(saved.name) });
			await cancel();
		} catch (e) {
			const failure = e as { status?: number; body?: { message?: string }; message?: string };
			if (failure.status === 401) window.location.href = `/cms-admin/login?redirect=${encodeURIComponent(location.pathname)}`;
			error = failure.body?.message ?? failure.message ?? 'La section n’a pas pu être enregistrée.';
		} finally {
			busy = false;
		}
	}
</script>

{#if count > 0}
	<button
		type="button"
		class="ui-icon-btn is-quiet ui-tip-below"
		bind:this={toggle}
		disabled={issue !== null}
		aria-label="Enregistrer comme section"
		data-tip="Enregistrer comme section : réutiliser {count > 1 ? 'ces blocs' : 'ce bloc'} dans « Mes sections »"
		aria-expanded={open}
		aria-controls={open ? 'save-section-form' : undefined}
		aria-describedby={issue ? 'save-section-issue' : undefined}
		onclick={start}
		data-test-save-section><BookmarkPlus class="ui-ic" /></button
	>
	{#if issue}<p id="save-section-issue" class="save-line ui-help">{issue}</p>{/if}
	{#if open}
		<form id="save-section-form" class="save-line save-form ui-stack" onsubmit={submit} novalidate>
			<label class="ui-label" for="save-section-name">Nom de la section</label>
			<input
				id="save-section-name"
				class="ui-input"
				bind:this={input}
				bind:value={name}
				maxlength={SECTION_NAME_MAX}
				autocomplete="off"
				placeholder="Ex. Bandeau offre de printemps"
				onkeydown={(event) => {
					if (event.key === 'Escape') {
						event.preventDefault();
						void cancel();
					}
				}}
				aria-invalid={error ? 'true' : undefined}
				aria-describedby={error ? 'save-section-help save-section-error' : 'save-section-help'}
			/>
			<p id="save-section-help" class="ui-help">
				{count > 1 ? `Les ${count} blocs sélectionnés gardent leur contenu et leur style.` : 'Le bloc garde son contenu et son style.'} La section s’ajoute à « Mes sections » (onglet Blocs), pour tous les administrateurs.
			</p>
			{#if error}<p id="save-section-error" class="ui-error" role="alert">{error}</p>{/if}
			<div class="ui-actions">
				<button type="submit" class="ui-btn" disabled={busy} data-test-save-section-submit>{busy ? 'Enregistrement…' : 'Enregistrer la section'}</button>
				<button type="button" class="ui-btn" onclick={cancel}>Annuler</button>
			</div>
		</form>
	{/if}
{/if}

<style>
	/* Under the head line, on the whole width of the panel. */
	.save-line {
		flex: 0 0 100%;
		margin: 0;
		padding-bottom: var(--ui-space-2);
		color: var(--ui-text);
	}
	.save-form {
		padding-bottom: var(--ui-space-3);
	}
</style>
