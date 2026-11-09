<script lang="ts">
	// « Enregistrer comme section » (D12), under the panel of the selected block: asks a name, then saves the
	// selected blocks (one, or several selected with Maj) with their content and style; the section appears in
	// « Mes sections » of the palette, for every administrator. What Sylius would refuse is said before:
	// a block bound to the template, or, for a Charte account, a value outside the charte (a section is new
	// content, CharteGuard). Sylius stays the judge: its refusal is shown under the name.
	import { tick } from 'svelte';
	import type { Editor } from 'grapesjs';
	import { createSection, getSections } from '#lib/remote/builder.remote.js';
	import { selectedBlocks, type SelectedBlocks } from '#lib/editor/panels/sectionCanvas.js';
	import { cleanSectionName, saveIssue, savedMessage, SECTION_NAME_MAX, sectionNameIssue } from '#lib/editor/panels/sections.js';

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
	<div class="save-section">
		{#if open}
			<form onsubmit={submit} novalidate>
				<label for="save-section-name">Nom de la section</label>
				<input
					id="save-section-name"
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
				<p id="save-section-help" class="hint">
					{count > 1 ? `Les ${count} blocs sélectionnés gardent leur contenu et leur style.` : 'Le bloc garde son contenu et son style.'} La section s’ajoute à « Mes sections » (onglet Blocs), pour tous les administrateurs.
				</p>
				{#if error}<p id="save-section-error" class="error" role="alert">{error}</p>{/if}
				<div class="buttons">
					<button type="submit" class="yb-btn yb-btn-primary" disabled={busy} data-test-save-section-submit>{busy ? 'Enregistrement…' : 'Enregistrer la section'}</button>
					<button type="button" class="yb-btn yb-btn-outline" onclick={cancel}>Annuler</button>
				</div>
			</form>
		{:else}
			<button
				type="button"
				class="yb-btn yb-btn-outline"
				bind:this={toggle}
				disabled={issue !== null}
				aria-describedby={issue ? 'save-section-issue' : undefined}
				onclick={start}
				data-test-save-section>Enregistrer comme section</button
			>
			{#if issue}<p id="save-section-issue" class="issue">{issue}</p>{/if}
		{/if}
	</div>
{/if}

<style>
	.save-section {
		margin-top: 1rem;
		padding-top: 1rem;
		border-top: 1px solid var(--yack-border);
	}
	label {
		display: block;
		margin-bottom: 0.3rem;
		font-size: 0.8rem;
		font-weight: 600;
		color: var(--yack-dark);
	}
	input {
		width: 100%;
		box-sizing: border-box;
		padding: 0.45rem 0.55rem;
		font: inherit;
		font-size: 0.85rem;
		color: var(--yack-dark);
		background: transparent;
		border: 1px solid var(--yack-border);
		border-radius: 0.375rem;
	}
	.hint {
		margin: 0.4rem 0 0;
		font-size: 0.75rem;
		color: var(--yack-gray);
	}
	.error,
	.issue {
		margin: 0.4rem 0 0;
		font-size: 0.78rem;
		font-weight: 600;
		color: var(--yack-magenta-dark);
	}
	.buttons {
		display: flex;
		flex-wrap: wrap;
		gap: 0.4rem;
		margin-top: 0.6rem;
	}
	input:focus-visible,
	.save-section :global(.yb-btn:focus-visible) {
		outline: 2px solid var(--yack-magenta);
		outline-offset: 2px;
	}
</style>
