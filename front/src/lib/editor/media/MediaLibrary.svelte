<script lang="ts">
	// Media library window of the builder (D7), opened by an image field (#lib/editor/traits/YackImage.svelte):
	// grid of thumbnails with a search on the name, upload by drag and drop or button (several files, one
	// submission of the remote form `uploadMedia` per file, progress of the batch, refusals of the API shown
	// as they are), detail of the selected image (dimensions, weight, default alternative text editable).
	// Modal <dialog>: the page behind is inert, Tab stays in the window, Échap closes it (the field gets the
	// focus back). Keys pressed in the window do not reach the editor shortcuts (Suppr, Ctrl+Z…).
	import { onDestroy, onMount, tick } from 'svelte';
	import { rovingIndex } from '#lib/editor/panels/roving.js';
	import { getMediaLibrary, saveMediaAlt, uploadMedia } from '#lib/remote/builder.remote.js';
	import { batchProgress, formatBytes, formatDimensions, MEDIA_ACCEPT, MEDIA_ALT_MAX, mediaIssue, type MediaItem } from './media.js';

	let {
		current,
		label,
		onchoose,
		onclose
	}: {
		/** Path of the image of the field ('' when none): selected when the window opens. */
		current: string;
		/** Label of the field (« Visuel », « Tuile 2 : visuel »). */
		label: string;
		onchoose: (item: MediaItem) => void;
		onclose: () => void;
	} = $props();

	type Upload = { key: number; file: File; name: string; size: number; status: 'waiting' | 'sending' | 'sent' | 'refused'; message: string };

	const id = $props.id();
	let dialog: HTMLDialogElement;
	let searchInput: HTMLInputElement;
	let uploadInput: HTMLInputElement | undefined = $state();
	let pickInput: HTMLInputElement;
	let search = $state('');
	let term = $state('');
	// svelte-ignore state_referenced_locally
	let selectedPath = $state(current);
	let focusIndex = $state<number | null>(null);
	let uploads = $state<Upload[]>([]);
	let running = $state(false);
	let dragging = $state(false);
	let altDraft = $state<string | null>(null);
	let altStatus = $state<{ kind: 'ok' | 'error'; text: string } | null>(null);
	let savingAlt = $state(false);
	let destroyed = false;
	let uploadKey = 0;

	const library = $derived(getMediaLibrary(term));
	const items = $derived(library.current ?? []);
	const selected = $derived(items.find((item) => item.path === selectedPath) ?? null);
	const selectedIndex = $derived(items.findIndex((item) => item.path === selectedPath));
	const tabStop = $derived(focusIndex ?? Math.max(0, selectedIndex));
	const progress = $derived(batchProgress(uploads.map((upload) => ({ size: upload.size, done: upload.status === 'sent' || upload.status === 'refused' }))));
	const sending = $derived(uploads.find((upload) => upload.status === 'sending') ?? null);
	const sentCount = $derived(uploads.filter((upload) => upload.status === 'sent').length);
	const refusedCount = $derived(uploads.filter((upload) => upload.status === 'refused').length);
	const loadError = $derived(library.error ? (library.error.message ?? 'La médiathèque n’a pas pu être lue.') : null);

	// Debounced search (the API filters on the name).
	$effect(() => {
		const next = search.trim();
		const timer = setTimeout(() => (term = next), 250);
		return () => clearTimeout(timer);
	});

	onMount(() => {
		dialog.showModal();
		searchInput.focus();
	});

	onDestroy(() => {
		destroyed = true;
	});

	// The image of the field comes into view once the library is read (an upload, at the top of the grid
	// under the report of the batch, does not scroll: the refusals stay in sight).
	let scrolled = false;
	$effect(() => {
		if (scrolled || selectedIndex < 0) return;
		scrolled = true;
		void tick().then(() => dialog?.querySelector<HTMLElement>(`[data-media-index="${selectedIndex}"]`)?.scrollIntoView({ block: 'nearest' }));
	});

	function close() {
		if (dialog?.open) dialog.close();
		onclose();
	}

	function choose(item: MediaItem | null) {
		if (!item) return;
		if (dialog?.open) dialog.close();
		onchoose(item);
	}

	function select(item: MediaItem) {
		selectedPath = item.path;
		altDraft = null;
		altStatus = null;
	}

	/** Tab and Maj+Tab go round the window; no key reaches the editor shortcuts. */
	function onkeydown(event: KeyboardEvent) {
		event.stopPropagation();
		if (event.key !== 'Tab') return;
		const focusables = [...dialog.querySelectorAll<HTMLElement>('button, input, textarea, select, [tabindex]:not([tabindex="-1"])')].filter(
			(element) => !element.hasAttribute('disabled') && element.getClientRects().length > 0 && !element.closest('[hidden]')
		);
		const first = focusables[0];
		const last = focusables[focusables.length - 1];
		if (!first || !last) return;
		if (event.shiftKey && (document.activeElement === first || !dialog.contains(document.activeElement))) {
			event.preventDefault();
			last.focus();
		} else if (!event.shiftKey && document.activeElement === last) {
			event.preventDefault();
			first.focus();
		}
	}

	function gridKeydown(event: KeyboardEvent) {
		if (event.key === 'Enter') {
			event.preventDefault();
			choose(items[tabStop] ?? null);
			return;
		}
		if (event.key === ' ') {
			event.preventDefault();
			const item = items[tabStop];
			if (item) select(item);
			return;
		}
		const next = rovingIndex(event.key, tabStop, items.length);
		if (next === null) return;
		event.preventDefault();
		focusIndex = next;
		const item = items[next];
		if (item) select(item);
		dialog.querySelector<HTMLElement>(`[data-media-index="${next}"]`)?.focus();
	}

	// ── Upload: every file checked here first (same messages as the API), then sent one by one ──

	function enqueue(files: Iterable<File>) {
		const added = [...files];
		if (added.length === 0) return;
		// The new images are at the top of the whole library.
		search = '';
		term = '';
		for (const file of added) {
			const issue = mediaIssue(file);
			uploads.push({ key: ++uploadKey, file, name: file.name, size: file.size, status: issue ? 'refused' : 'waiting', message: issue ?? '' });
		}
		// The report of the batch (progress, refusals) is at the top of the grid.
		void tick().then(() => dialog?.querySelector('.drop')?.scrollTo({ top: 0 }));
		if (!running) void run();
	}

	async function run() {
		running = true;
		let last: MediaItem | null = null;
		try {
			for (let next = uploads.find((upload) => upload.status === 'waiting'); next && !destroyed; next = uploads.find((upload) => upload.status === 'waiting')) {
				const upload = next;
				upload.status = 'sending';
				if (!uploadInput) break;
				const transfer = new DataTransfer();
				transfer.items.add(upload.file);
				uploadInput.files = transfer.files;
				try {
					const ok = await uploadMedia.submit().updates(getMediaLibrary(''));
					const created = uploadMedia.result;
					if (ok && created) {
						upload.status = 'sent';
						last = created;
					} else {
						upload.status = 'refused';
						upload.message = uploadMedia.fields.allIssues()?.[0]?.message ?? 'Envoi refusé.';
					}
				} catch (e) {
					upload.status = 'refused';
					upload.message = failureMessage(e, 'L’envoi du fichier a échoué : réessayez.');
				}
			}
		} finally {
			running = false;
			if (uploadInput) uploadInput.value = '';
		}
		if (last && !destroyed) select(last);
	}

	function failureMessage(e: unknown, fallback: string): string {
		const failure = e as { status?: number; body?: { message?: string }; message?: string };
		if (failure?.status === 401) return 'Session expirée : reconnectez-vous.';
		return failure?.body?.message ?? fallback;
	}

	function ondrop(event: DragEvent) {
		event.preventDefault();
		dragging = false;
		enqueue(event.dataTransfer?.files ?? []);
	}

	async function saveAlt() {
		if (!selected || altDraft === null) return;
		savingAlt = true;
		altStatus = null;
		try {
			const saved = await saveMediaAlt({ id: selected.id, alt: altDraft }).updates(getMediaLibrary(term));
			altDraft = null;
			altStatus = { kind: 'ok', text: saved.alt === '' ? 'Texte alternatif retiré.' : 'Texte alternatif enregistré.' };
		} catch (e) {
			altStatus = { kind: 'error', text: failureMessage(e, 'Le texte alternatif n’a pas pu être enregistré.') };
		} finally {
			savingAlt = false;
		}
	}

	const formatDate = (value: string) => {
		const date = new Date(value);
		return Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
	};
</script>

<!-- svelte-ignore a11y_no_noninteractive_element_interactions -->
<dialog
	bind:this={dialog}
	class="media"
	aria-labelledby="{id}-title"
	aria-describedby="{id}-for"
	oncancel={(event) => {
		event.preventDefault();
		close();
	}}
	{onkeydown}
	data-test-media
>
	<header class="head">
		<div>
			<h2 id="{id}-title">Médiathèque</h2>
			<p id="{id}-for" class="for">Image du champ « {label} »</p>
		</div>
		<button type="button" class="close" aria-label="Fermer la médiathèque" onclick={close} data-test-media-close>×</button>
	</header>

	<div class="tools">
		<label class="search">
			<span class="visually-hidden">Rechercher une image par son nom</span>
			<input bind:this={searchInput} type="search" placeholder="Rechercher une image" bind:value={search} autocomplete="off" data-test-media-search />
		</label>
		<input
			bind:this={pickInput}
			id="{id}-pick"
			class="visually-hidden"
			type="file"
			multiple
			accept={MEDIA_ACCEPT}
			tabindex="-1"
			onchange={(event) => {
				enqueue(event.currentTarget.files ?? []);
				event.currentTarget.value = '';
			}}
			data-test-media-pick
		/>
		<button type="button" class="yb-btn yb-btn-outline" onclick={() => pickInput.click()} data-test-media-upload>Envoyer des images</button>
	</div>

	<form {...uploadMedia} enctype="multipart/form-data" hidden>
		<input {...uploadMedia.fields.file.as('file')} bind:this={uploadInput} />
	</form>

	<div class="body">
		<section
			class="drop"
			class:dragging
			aria-label="Images de la médiathèque"
			ondragover={(event) => {
				event.preventDefault();
				dragging = true;
			}}
			ondragleave={(event) => {
				if (!event.currentTarget.contains(event.relatedTarget as Node | null)) dragging = false;
			}}
			{ondrop}
			data-test-media-drop
		>
			<p class="hint">Glissez des images ici, ou « Envoyer des images ». JPEG, PNG ou WebP, 20 Mo au plus par image.</p>

			{#if uploads.length > 0}
				<div class="uploads" data-test-media-uploads>
					<div class="progress">
						<label for="{id}-progress">
							{#if sending}Envoi de « {sending.name} » ({uploads.indexOf(sending) + 1} sur {uploads.length})
							{:else}{sentCount} image{sentCount > 1 ? 's' : ''} envoyée{sentCount > 1 ? 's' : ''}{#if refusedCount > 0}, {refusedCount} refusée{refusedCount > 1 ? 's' : ''}{/if}{/if}
						</label>
						<progress id="{id}-progress" max="100" value={Math.round(progress * 100)} data-test-media-progress>{Math.round(progress * 100)} %</progress>
					</div>
					<ul aria-live="polite">
						{#each uploads as upload (upload.key)}
							<li class={upload.status} data-test-media-upload-row={upload.status}>
								<span class="file">{upload.name}</span>
								<span class="size">{formatBytes(upload.size)}</span>
								{#if upload.status === 'sending'}<progress aria-label="Envoi de {upload.name} en cours"></progress>
								{:else if upload.status === 'waiting'}<span class="state">En attente</span>
								{:else if upload.status === 'sent'}<span class="state">Envoyée</span>
								{:else}<span class="state" role="alert">{upload.message}</span>{/if}
							</li>
						{/each}
					</ul>
					{#if !running}<button type="button" class="link" onclick={() => (uploads = [])}>Effacer la liste des envois</button>{/if}
				</div>
			{/if}

			{#if loadError}
				<p class="error" role="alert">{loadError}</p>
			{:else if library.loading && items.length === 0}
				<p class="hint" role="status">Chargement de la médiathèque…</p>
			{:else if items.length === 0}
				<p class="hint" role="status">{term ? `Aucune image dont le nom contient « ${term} ».` : 'La médiathèque est vide.'}</p>
			{/if}

			{#if items.length > 0}
				<div class="grid" role="listbox" tabindex="-1" aria-label="Images" aria-describedby="{id}-keys" onkeydown={gridKeydown} onfocusout={() => (focusIndex = null)} data-test-media-grid>
					{#each items as item, index (item.id)}
						<!-- Keys of the options: gridKeydown on the listbox (arrows, Entrée, Espace). -->
						<!-- svelte-ignore a11y_click_events_have_key_events -->
						<div
							role="option"
							class="tile"
							tabindex={index === tabStop ? 0 : -1}
							aria-selected={item.path === selectedPath}
							data-media-index={index}
							data-test-media-item={item.path}
							onclick={() => select(item)}
							ondblclick={() => choose(item)}
						>
							<img src={item.thumbnail} alt="" loading="lazy" />
							<span class="tile-name">{item.name}</span>
						</div>
					{/each}
				</div>
				<p class="visually-hidden" id="{id}-keys">Flèches pour parcourir, Entrée pour choisir l’image.</p>
			{/if}
		</section>

		<aside class="detail" aria-label="Détail de l’image" data-test-media-detail>
			{#if selected}
				<img class="preview" src={selected.thumbnail} alt="" />
				<p class="detail-name">{selected.name}</p>
				<dl>
					<dt>Dimensions</dt>
					<dd data-test-media-dimensions>{formatDimensions(selected)}</dd>
					<dt>Poids</dt>
					<dd data-test-media-size>{formatBytes(selected.size)}</dd>
					{#if selected.uploaded}
						<dt>Envoyée par</dt>
						<dd>{selected.author ?? 'Inconnu'}{#if formatDate(selected.createdAt)}, le {formatDate(selected.createdAt)}{/if}</dd>
					{:else}
						<dt>Origine</dt>
						<dd>Visuel du site Yack</dd>
					{/if}
				</dl>
				<label class="alt">
					<span>Texte alternatif par défaut</span>
					<textarea
						rows="2"
						maxlength={MEDIA_ALT_MAX}
						value={altDraft ?? selected.alt}
						oninput={(event) => {
							altDraft = event.currentTarget.value;
							altStatus = null;
						}}
						aria-describedby="{id}-alt-help"
						data-test-media-alt
					></textarea>
				</label>
				<small class="help" id="{id}-alt-help">Repris par un bloc quand on y choisit l’image ; chaque bloc peut ensuite le modifier.</small>
				<div class="alt-actions">
					<button type="button" class="yb-btn yb-btn-outline" disabled={altDraft === null || altDraft.trim() === selected.alt || savingAlt} onclick={saveAlt} data-test-media-alt-save
						>Enregistrer le texte</button
					>
					{#if altStatus}<span class={altStatus.kind} role={altStatus.kind === 'error' ? 'alert' : 'status'}>{altStatus.text}</span>{/if}
				</div>
			{:else}
				<p class="hint">Choisissez une image dans la grille pour voir son détail.</p>
			{/if}
		</aside>
	</div>

	<footer class="foot">
		<button type="button" class="yb-btn yb-btn-outline" onclick={close}>Annuler</button>
		<button type="button" class="yb-btn yb-btn-primary" disabled={!selected} onclick={() => choose(selected)} data-test-media-choose>Choisir cette image</button>
	</footer>
</dialog>

<style>
	.media {
		width: min(1100px, calc(100vw - 2rem));
		max-height: calc(100vh - 2rem);
		padding: 0;
		border: 0;
		border-radius: 0.5rem;
		box-shadow: 0 1rem 3rem color-mix(in srgb, var(--yack-dark) 30%, transparent);
		color: var(--yack-dark);
		font-size: 0.9rem;
	}
	.media[open] {
		display: flex;
		flex-direction: column;
	}
	.media::backdrop {
		background: color-mix(in srgb, var(--yack-dark) 45%, transparent);
	}
	.visually-hidden {
		position: absolute;
		width: 1px;
		height: 1px;
		overflow: hidden;
		clip-path: inset(50%);
		white-space: nowrap;
	}
	.head,
	.tools,
	.foot {
		display: flex;
		align-items: center;
		gap: 0.75rem;
		padding: 0.75rem 1.25rem;
	}
	.head {
		justify-content: space-between;
		border-bottom: 1px solid var(--yack-border);
	}
	h2 {
		margin: 0;
		font-size: 1.1rem;
	}
	.for {
		margin: 0.15rem 0 0;
		font-size: 0.8rem;
		color: var(--yack-gray);
	}
	.close {
		width: 2.25rem;
		height: 2.25rem;
		font-size: 1.4rem;
		line-height: 1;
		color: var(--yack-dark);
		background: transparent;
		border: 1px solid var(--yack-border);
		border-radius: 0.375rem;
		cursor: pointer;
	}
	.search {
		flex: 1;
	}
	.search input {
		width: 100%;
		box-sizing: border-box;
		padding: 0.5rem 0.6rem;
		font: inherit;
		border: 1px solid var(--yack-border);
		border-radius: 0.375rem;
	}
	.body {
		display: grid;
		grid-template-columns: minmax(0, 1fr) 18rem;
		min-height: 0;
		flex: 1;
		border-top: 1px solid var(--yack-border);
		border-bottom: 1px solid var(--yack-border);
	}
	.drop {
		min-height: 20rem;
		max-height: calc(100vh - 14rem);
		overflow-y: auto;
		padding: 1rem 1.25rem;
		outline: 2px dashed transparent;
		outline-offset: -0.5rem;
	}
	.drop.dragging {
		outline-color: var(--yack-magenta);
		background: color-mix(in srgb, var(--yack-magenta) 6%, transparent);
	}
	.hint {
		margin: 0 0 0.75rem;
		font-size: 0.8rem;
		color: var(--yack-gray);
	}
	.error {
		margin: 0 0 0.75rem;
		font-weight: 600;
		color: var(--yack-magenta-dark);
	}
	.uploads {
		margin: 0 0 1rem;
		padding: 0.75rem;
		border: 1px solid var(--yack-border);
		border-radius: 0.375rem;
	}
	.progress {
		display: grid;
		gap: 0.3rem;
		font-size: 0.8rem;
		font-weight: 600;
	}
	.progress progress {
		width: 100%;
		accent-color: var(--yack-magenta);
	}
	.uploads ul {
		margin: 0.5rem 0 0;
		padding: 0;
		list-style: none;
	}
	.uploads li {
		display: grid;
		grid-template-columns: minmax(0, 1fr) auto minmax(8rem, 45%);
		gap: 0.6rem;
		align-items: center;
		padding: 0.3rem 0;
		font-size: 0.8rem;
		border-top: 1px solid var(--yack-border);
	}
	.uploads li progress {
		width: 100%;
		accent-color: var(--yack-magenta);
	}
	.file {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	.size {
		color: var(--yack-gray);
	}
	.uploads li.refused .state {
		font-weight: 600;
		color: var(--yack-magenta-dark);
	}
	.uploads li.sent .state {
		color: var(--yack-gray);
	}
	.link {
		margin-top: 0.4rem;
		padding: 0;
		font: inherit;
		font-size: 0.8rem;
		color: var(--yack-magenta-dark);
		background: none;
		border: 0;
		text-decoration: underline;
		cursor: pointer;
	}
	.grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(8.5rem, 1fr));
		gap: 0.75rem;
	}
	.tile {
		display: grid;
		gap: 0.35rem;
		padding: 0.4rem;
		border: 2px solid transparent;
		border-radius: 0.375rem;
		cursor: pointer;
	}
	.tile:hover {
		background: color-mix(in srgb, var(--yack-magenta) 6%, transparent);
	}
	.tile[aria-selected='true'] {
		border-color: var(--yack-magenta);
		background: color-mix(in srgb, var(--yack-magenta) 10%, transparent);
	}
	.tile:focus-visible {
		outline: 2px solid var(--yack-dark);
		outline-offset: 2px;
	}
	.tile img {
		width: 100%;
		aspect-ratio: 4 / 3;
		object-fit: contain;
		border-radius: 0.25rem;
		background: color-mix(in srgb, var(--yack-border) 60%, transparent);
	}
	.tile-name {
		overflow: hidden;
		font-size: 0.75rem;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	.detail {
		display: grid;
		align-content: start;
		gap: 0.5rem;
		max-height: calc(100vh - 14rem);
		overflow-y: auto;
		padding: 1rem 1.25rem;
		border-left: 1px solid var(--yack-border);
	}
	.preview {
		width: 100%;
		max-height: 12rem;
		object-fit: contain;
		border-radius: 0.25rem;
		background: color-mix(in srgb, var(--yack-border) 60%, transparent);
	}
	.detail-name {
		margin: 0;
		font-weight: 600;
		overflow-wrap: anywhere;
	}
	dl {
		display: grid;
		grid-template-columns: auto 1fr;
		gap: 0.25rem 0.75rem;
		margin: 0;
		font-size: 0.8rem;
	}
	dt {
		color: var(--yack-gray);
	}
	dd {
		margin: 0;
	}
	.alt {
		display: grid;
		gap: 0.3rem;
		font-size: 0.8rem;
		font-weight: 600;
	}
	.alt textarea {
		padding: 0.45rem 0.55rem;
		font: inherit;
		font-weight: 400;
		border: 1px solid var(--yack-border);
		border-radius: 0.375rem;
		resize: vertical;
	}
	.help {
		font-size: 0.75rem;
		color: var(--yack-gray);
	}
	.alt-actions {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 0.5rem;
		font-size: 0.8rem;
	}
	.alt-actions .ok {
		color: var(--yack-gray);
	}
	.alt-actions .error {
		margin: 0;
	}
	.foot {
		justify-content: flex-end;
	}
	.media :is(input, textarea, button):focus-visible {
		outline: 2px solid var(--yack-magenta);
		outline-offset: 1px;
	}
</style>
