<script lang="ts">
	// « Historique » panel of the builder (D11): the revisions of the page or template, the most recent
	// first (« Aujourd'hui 14 h 05, Sophie Martin, publiée »). Choosing one asks the shell to show it in
	// the canvas (read-only); the shell owns the canvas, the banner and the restore. Focus: the heading on
	// opening, Échap goes back to the current version (or closes the panel), arrows move in the list.
	import { onMount, tick } from 'svelte';
	import {
		blockCountLabel,
		revisionLine,
		versionCountLabel,
		type RevisionSummary,
		type RevisionTarget
	} from '#lib/editor/panels/history.js';
	import { getRevisions } from '#lib/remote/builder.remote.js';

	let {
		target,
		current,
		saves,
		onselect,
		onescape,
		onclose
	}: {
		target: RevisionTarget;
		/** Revision shown in the canvas, null for the current version. */
		current: number | null;
		/** Number of saves since the editor opened: the list is read again after each one. */
		saves: number;
		/** A revision is chosen; `stored` is the most recent one (the stored content). */
		onselect: (revision: RevisionSummary, stored: RevisionSummary) => void;
		/** Échap in the panel. */
		onescape: () => void;
		onclose: () => void;
	} = $props();

	let heading: HTMLHeadingElement;
	let list = $state<HTMLOListElement>();
	/** Date the labels are computed against (« Aujourd'hui »): the time of the last reading. */
	let now = $state(new Date());

	const revisions = $derived(getRevisions(target));
	const items = $derived(revisions.current ?? []);
	const loadError = $derived(revisions.error ? (revisions.error.message ?? 'L’historique n’a pas pu être lu.') : null);

	function reload() {
		now = new Date();
		void revisions.refresh();
	}

	onMount(() => {
		heading.focus();
		// A list kept from an earlier opening is read again: saves may have been made since.
		if (revisions.current) reload();
	});

	// svelte-ignore state_referenced_locally
	let seenSaves = saves;
	$effect(() => {
		if (saves === seenSaves) return;
		seenSaves = saves;
		reload();
	});

	/** Gives the focus back to the button of a revision (after « Revenir à la version en cours »). */
	export async function focusRevision(id: number): Promise<void> {
		await tick();
		list?.querySelector<HTMLButtonElement>(`button[data-revision="${id}"]`)?.focus();
	}

	function onkeydown(event: KeyboardEvent) {
		if (event.key === 'Escape') {
			event.preventDefault();
			onescape();
			return;
		}
		if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
		const buttons = [...(list?.querySelectorAll<HTMLButtonElement>('button[data-revision]') ?? [])];
		const index = buttons.indexOf(document.activeElement as HTMLButtonElement);
		if (index < 0) return;
		event.preventDefault();
		buttons[Math.min(buttons.length - 1, Math.max(0, index + (event.key === 'ArrowDown' ? 1 : -1)))]?.focus();
	}
</script>

<!-- svelte-ignore a11y_no_noninteractive_element_interactions -->
<section id="history-panel" class="history" aria-labelledby="history-title" {onkeydown}>
	<div class="head">
		<h2 id="history-title" tabindex="-1" bind:this={heading}>Historique</h2>
		<button type="button" class="close" onclick={onclose} aria-label="Fermer l’historique">×</button>
	</div>
	<p class="hint">
		{#if items.length > 0}{versionCountLabel(items.length)} (les 50 dernières sont gardées).{/if}
		Choisissez une version pour l’afficher dans le canevas, en lecture seule. Rien n’est modifié tant que vous ne la restaurez pas.
	</p>

	{#if loadError}
		<p class="error" role="alert">{loadError} <button type="button" class="retry" onclick={reload}>Réessayer</button></p>
	{:else if revisions.loading && items.length === 0}
		<p class="hint" role="status">Chargement de l’historique…</p>
	{:else if items.length === 0}
		<p class="hint">{versionCountLabel(0)} : la première apparaîtra au prochain enregistrement.</p>
	{:else}
		<ol class="revisions" bind:this={list} aria-label="Versions enregistrées, de la plus récente à la plus ancienne">
			{#each items as revision, index (revision.id)}
				<li>
					<button
						type="button"
						class:current={revision.id === current}
						aria-current={revision.id === current ? 'true' : undefined}
						onclick={() => onselect(revision, items[0] ?? revision)}
						data-revision={revision.id}
					>
						<span class="line">{revisionLine(revision, now)}</span>
						<span class="detail">
							{revision.title} · {blockCountLabel(revision.blockCount)}{#if index === 0}{' · '}<span class="stored">version enregistrée</span>{/if}
						</span>
					</button>
				</li>
			{/each}
		</ol>
	{/if}
</section>

<style>
	.history {
		display: flex;
		flex-direction: column;
		gap: 0.25rem;
	}
	.head {
		display: flex;
		align-items: center;
		justify-content: space-between;
	}
	.head h2 {
		margin: 0;
	}
	.head h2:focus-visible,
	.revisions button:focus-visible,
	.close:focus-visible,
	.retry:focus-visible {
		outline: 2px solid var(--yack-magenta);
		outline-offset: 2px;
	}
	.close {
		background: none;
		border: 0;
		font-size: 1.3rem;
		line-height: 1;
		cursor: pointer;
		color: var(--yack-gray);
	}
	.hint {
		font-size: 0.8rem;
		color: var(--yack-gray);
		margin: 0 0 0.75rem;
	}
	.error {
		font-size: 0.85rem;
		color: var(--yack-magenta-dark);
	}
	.retry {
		background: none;
		border: 0;
		padding: 0;
		font: inherit;
		color: inherit;
		text-decoration: underline;
		cursor: pointer;
	}
	.revisions {
		list-style: none;
		margin: 0;
		padding: 0;
		display: grid;
		gap: 0.4rem;
	}
	.revisions button {
		display: flex;
		flex-direction: column;
		gap: 0.15rem;
		width: 100%;
		padding: 0.55rem 0.7rem;
		text-align: left;
		font: inherit;
		color: var(--yack-dark);
		background: transparent;
		border: 1px solid var(--yack-border);
		border-radius: 0.5rem;
		cursor: pointer;
	}
	.revisions button:hover {
		border-color: var(--yack-magenta);
	}
	.revisions button.current {
		border: 2px solid var(--yack-magenta);
		padding: calc(0.55rem - 1px) calc(0.7rem - 1px);
	}
	.line {
		font-size: 0.85rem;
		font-weight: 600;
	}
	.detail {
		font-size: 0.75rem;
		color: var(--yack-gray);
	}
	.stored {
		color: var(--yack-magenta);
		font-weight: 600;
	}
</style>
