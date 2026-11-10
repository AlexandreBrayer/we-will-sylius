<script lang="ts" module>
	export interface ShellPanel {
		id: string;
		label: string;
		body: Snippet;
	}
</script>

<script lang="ts">
	// GrapesJS editor of the builder, shared by CMS pages and templates: toolbar (level of the account,
	// devices, view, draft / publish), left panel "Blocs | Structure" (palette, component tree), canvas,
	// right panel with the caller's panels (page / template settings, assignments…) and the selected
	// block (panels/BlockPanel.svelte: Contenu, Style, Avancé). « Historique » (D11) replaces the right
	// panel by the revisions (panels/History.svelte); a revision is shown in the canvas, read-only, under
	// a banner, and « Restaurer » loads it into the editor without saving (panels/revisionCanvas.ts).
	// Sections (D12): « Mes sections » under the palette (panels/SectionPalette.svelte) inserts a copy of
	// a saved section; « Enregistrer comme section » under the selected block (panels/SaveSection.svelte).
	// Edit lock (D13, lock.ts): taken at the opening, kept alive, given back at the closing; while another
	// administrator holds it, a banner says who and since when, the canvas is read-only (lockCanvas.ts),
	// palette, panels, history and saves wait, and « Prendre la main » takes it over (then reloads).
	import 'grapesjs/dist/css/grapes.min.css';
	import { onMount, tick, type Snippet } from 'svelte';
	import type { Editor } from 'grapesjs';
	import type { YackContext } from '#lib/blocks/context.js';
	import type { PageBlock, PageStatus, TemplateType } from '#lib/blocks/types.js';
	import BackLink from '#lib/admin/BackLink.svelte';
	import BlockPanel from '#lib/editor/panels/BlockPanel.svelte';
	import { LEVEL_LABELS, parseBuilderMe, type BuilderMe } from '#lib/editor/panels/blockStyle.js';
	import type { CanvasChrome } from '#lib/editor/grapesSetup.js';
	import { DROP_REFUSED_EVENT } from '#lib/editor/layouts.js';
	import ComponentTree from '#lib/editor/tree/ComponentTree.svelte';
	import { installTree } from '#lib/editor/tree/componentTree.js';
	import { loadTraitTypes } from '#lib/editor/traits/registerTraits.js';
	import { getBuilderMe } from '#lib/remote/builder.remote.js';
	import History from '#lib/editor/panels/History.svelte';
	import {
		authorLabel,
		charteWarning,
		newDeviations,
		statusLabel as revisionStatusLabel,
		versionLabel,
		type Revision,
		type RevisionSummary,
		type RevisionTarget
	} from '#lib/editor/panels/history.js';
	import { openPreview, restoreRevision, type RevisionPreview } from '#lib/editor/panels/revisionCanvas.js';
	import { getRevision } from '#lib/remote/builder.remote.js';
	import SaveSection from '#lib/editor/panels/SaveSection.svelte';
	import SectionPalette from '#lib/editor/panels/SectionPalette.svelte';
	import { createLockSession, INITIAL_VIEW, isReadOnly, lockNotice, takeoverQuestion, type LockSession, type LockTarget, type LockView } from '#lib/editor/lock.js';
	import { lockCanvas } from '#lib/editor/lockCanvas.js';
	import { editLock, readEditLock } from '#lib/remote/builder.remote.js';
	import { paletteBlocks } from '#lib/blocks/registry.js';
	import { categoryId, initiallyOpen, insertPaletteBlock, normalizeSearch, paletteGroups, startPaletteDrag, toggled } from '#lib/editor/palette.js';
	import { blockThumb } from '#lib/editor/thumbs/thumbs.js';

	/** `slug` (page) or `id` (template): the content whose history « Historique » shows. */
	type Content = { blocks: PageBlock[]; grapesData: Record<string, unknown> | null; slug?: string; id?: number };

	let {
		title,
		subtitle,
		status,
		statusLabel,
		back,
		view,
		content,
		templateType,
		context,
		panels,
		chrome = null,
		tools,
		onsave,
		editor = $bindable(null),
		dirty = $bindable(false)
	}: {
		title: string;
		subtitle: string;
		status: PageStatus;
		statusLabel: string;
		back: { href: string; label: string };
		/** Shop page of the edited content (draft: preview URL). */
		view: { href: string; label: string } | null;
		/** Initial content (read once, when GrapesJS starts). */
		content: Content;
		/** null: CMS page (free blocks); otherwise the template type (its bound blocks too). */
		templateType: TemplateType | null;
		/** Context of the canvas blocks. */
		context: YackContext;
		/** Right panel tabs, before "Bloc sélectionné". */
		panels: ShellPanel[];
		/** Shop chrome rendered around the canvas content (read once, when GrapesJS starts). */
		chrome?: CanvasChrome | null;
		/** Extra toolbar controls (before the devices). */
		tools?: Snippet;
		/** Saves the content with `status`; resolves with the confirmation shown, rejects with the error. */
		onsave: (status: PageStatus, collected: { blocks: PageBlock[]; grapesData: Record<string, unknown> }) => Promise<string>;
		editor?: Editor | null;
		dirty?: boolean;
	} = $props();

	let leftTab = $state<'blocks' | 'tree'>('blocks');
	// svelte-ignore state_referenced_locally
	let rightTab = $state(panels[0]?.id ?? 'block');
	let canvasEl: HTMLDivElement;
	/** Level and charte of the account (fields of the Style tab, badge of the toolbar). */
	let me = $state<BuilderMe | null>(null);
	let device = $state('desktop');
	let saving = $state<PageStatus | null>(null);
	/** Published content: "Retirer du site" asks a confirmation in the toolbar before going back to draft. */
	let confirmUnpublish = $state(false);
	let message = $state<{ kind: 'ok' | 'error'; text: string } | null>(null);

	// ── History (D11) ──
	// svelte-ignore state_referenced_locally
	const revisionTarget: RevisionTarget | null =
		templateType === null
			? typeof content.slug === 'string' && content.slug !== '' ? { target: 'page', slug: content.slug } : null
			: typeof content.id === 'number' ? { target: 'template', id: content.id } : null;
	let historyOpen = $state(false);
	let historyButton = $state<HTMLButtonElement>();
	let historyPanel = $state<ReturnType<typeof History>>();
	let restoreConfirm = $state<HTMLButtonElement>();
	/** Successful saves since the editor opened (the history list is read again). */
	let saves = $state(0);
	/** Revision shown in the canvas (read-only) and what the banner says about it. */
	let preview = $state<{
		summary: RevisionSummary;
		revision: Revision | null;
		error: string | null;
		/** Charte account and free values removed since: Sylius will refuse to save it as it is. */
		warning: string | null;
		/** « Restaurer » with unsaved changes: asked twice. */
		confirming: boolean;
	} | null>(null);
	/** Edited components taken out of the canvas while a revision is shown. */
	let previewSession: RevisionPreview | null = null;
	let selection = 0;

	// ── Edit lock (D13) ──
	/** Same content as the history: a page by its slug, a template by its id. */
	const lockTarget: LockTarget | null = revisionTarget;
	// svelte-ignore state_referenced_locally
	const lockKind = templateType === null ? 'page' : 'template';
	let lockView = $state<LockView>(INITIAL_VIEW);
	let lockSession: LockSession | null = null;
	/** Canvas loaded (its page exists: it can be made read-only). */
	let canvasReady = $state(false);
	let takeoverAsk = $state(false);
	let takeoverBusy = $state(false);
	let takeoverConfirm = $state<HTMLButtonElement>();
	let lockAction = $state<HTMLButtonElement>();
	/** Reload after a take over: no « unsaved changes » warning, no release (the lock is ours). */
	let reloading = $state(false);
	const readOnly = $derived(isReadOnly(lockView) || reloading);
	const notice = $derived(lockNotice(lockView, lockKind, dirty));
	/** Message kept for the reload that follows a take over. */
	const TAKEN_KEY = 'yack-builder-main';
	const lockKey = lockTarget ? (lockTarget.target === 'page' ? `page:${lockTarget.slug}` : `template:${lockTarget.id}`) : '';

	// ── Palette (E6, #lib/editor/palette.ts): search, rubriques « Mise en page » first, help behind « ? » ──
	const paletteEntries = $derived(paletteBlocks(templateType));
	let paletteQuery = $state('');
	/** Rubriques left open (no search) and rubriques closed during the current search. */
	let paletteOpened = $state(initiallyOpen());
	let closedInSearch = $state<string[]>([]);
	const searching = $derived(normalizeSearch(paletteQuery) !== '');
	const paletteView = $derived(paletteGroups(paletteEntries, paletteQuery, paletteOpened, closedInSearch));
	let helpOpen = $state(false);
	let endPaletteDrag: ((event: DragEvent) => void) | null = null;

	function togglePaletteGroup(category: string) {
		if (searching) closedInSearch = toggled(closedInSearch, category);
		else paletteOpened = toggled(paletteOpened, category);
	}

	/** Échap in the search field: empties it (an empty field lets Échap go on). */
	function searchKeydown(event: KeyboardEvent) {
		if (event.key !== 'Escape' || paletteQuery === '') return;
		event.preventDefault();
		event.stopPropagation();
		paletteQuery = '';
		closedInSearch = [];
	}

	/** Échap on « ? »: hides the help (WCAG 1.4.13). */
	function helpKeydown(event: KeyboardEvent) {
		if (event.key !== 'Escape' || !helpOpen) return;
		event.stopPropagation();
		helpOpen = false;
	}

	function paletteDragStart(event: DragEvent, type: string) {
		if (!editor) {
			event.preventDefault();
			return;
		}
		endPaletteDrag = startPaletteDrag(editor, type, event);
	}

	function paletteDragEnd(event: DragEvent) {
		endPaletteDrag?.(event);
		endPaletteDrag = null;
	}

	onMount(() => {
		let destroyed = false;
		let uninstallTree: (() => void) | undefined;
		const account = Promise.resolve(getBuilderMe()).catch(() => {
			// Strictest reading: charte level, no free value (Sylius stays the judge).
			message = { kind: 'error', text: 'Votre niveau dans le builder n’a pas pu être lu : seules les valeurs de la charte sont proposées.' };
			return parseBuilderMe(null);
		});
		Promise.all([import('#lib/editor/grapesSetup.js'), account, loadTraitTypes()]).then(([{ initEditor }, loaded]) => {
			if (destroyed) return;
			me = loaded;
			const instance = initEditor({ container: canvasEl, content, templateType, context, me: loaded, chrome });
			uninstallTree = installTree(instance);
			instance.on(DROP_REFUSED_EVENT, (text: string) => (message = { kind: 'error', text }));
			instance.on('load', () => {
				canvasReady = true;
				instance.on('update', () => (dirty = true));
				instance.on('component:selected', () => (rightTab = 'block'));
			});
			editor = instance;
		});
		const beforeUnload = (event: BeforeUnloadEvent) => {
			if (dirty && !reloading) event.preventDefault();
		};
		window.addEventListener('beforeunload', beforeUnload);
		if (lockTarget) {
			const target = lockTarget;
			if (sessionStorage.getItem(TAKEN_KEY) === lockKey) {
				sessionStorage.removeItem(TAKEN_KEY);
				message = { kind: 'ok', text: `Vous avez la main sur ${lockKind === 'page' ? 'cette page' : 'ce template'} : la dernière version enregistrée est chargée.` };
			}
			lockSession = createLockSession(target, { act: (action, token) => editLock({ target, action, token }), read: () => readEditLock(target) }, lockChanged);
			void lockSession.start();
		}
		const pageHide = () => {
			if (!reloading) sendRelease();
		};
		const pageShow = (event: PageTransitionEvent) => {
			// Back from the back-forward cache: the lock was given back at `pagehide`; a sign of life takes it again if nobody took it.
			if (event.persisted) void lockSession?.check();
		};
		window.addEventListener('pagehide', pageHide);
		window.addEventListener('pageshow', pageShow);
		return () => {
			destroyed = true;
			window.removeEventListener('pagehide', pageHide);
			window.removeEventListener('pageshow', pageShow);
			if (reloading) lockSession?.stop();
			else lockSession?.release();
			lockSession = null;
			previewSession?.close();
			previewSession = null;
			window.removeEventListener('beforeunload', beforeUnload);
			uninstallTree?.();
			editor?.destroy();
			editor = null;
		};
	});

	/** A new lock view: becoming read-only ends the preview of a revision and closes the confirmations. */
	function lockChanged(view: LockView) {
		const wasReadOnly = isReadOnly(lockView);
		lockView = view;
		if (isReadOnly(view) && !wasReadOnly) {
			endPreview();
			historyOpen = false;
			confirmUnpublish = false;
		}
		if (!isReadOnly(view)) takeoverAsk = false;
	}

	/** Gives the lock back when the tab closes or leaves (sendBeacon: sent even while the page unloads). */
	function sendRelease() {
		const release = lockSession?.beacon();
		if (!release) return;
		const { target, token } = release;
		const body = JSON.stringify(target.target === 'page' ? { target: 'page', slug: target.slug, token } : { target: 'template', id: target.id, token });
		if (navigator.sendBeacon?.('/cms-admin/verrou', body)) return;
		void fetch('/cms-admin/verrou', { method: 'POST', body, keepalive: true, headers: { 'Content-Type': 'text/plain' } }).catch(() => undefined);
	}

	async function askTakeover() {
		takeoverAsk = true;
		await tick();
		takeoverConfirm?.focus();
	}

	async function cancelTakeover() {
		takeoverAsk = false;
		await tick();
		lockAction?.focus();
	}

	/** « Oui, prendre la main »: the lock is ours, then the page reloads on the saved version. */
	async function confirmTakeover() {
		if (!lockSession || takeoverBusy) return;
		takeoverBusy = true;
		reloading = true;
		if (await lockSession.takeover()) {
			lockSession.stop();
			sessionStorage.setItem(TAKEN_KEY, lockKey);
			window.location.reload();
			return;
		}
		reloading = false;
		takeoverBusy = false;
		takeoverAsk = false;
		message = { kind: 'error', text: 'La prise de main a échoué : réessayez dans un instant.' };
		// The focused confirmation is gone: the focus goes back to « Prendre la main ».
		await tick();
		lockAction?.focus();
	}

	function reloadSaved() {
		window.location.reload();
	}

	// Read-only canvas while another administrator holds the lock (and until the reload of a take over).
	$effect(() => {
		if (!editor || !canvasReady || !readOnly) return;
		return lockCanvas(editor);
	});

	function setDevice(id: string) {
		device = id;
		editor?.setDevice(id);
	}

	async function save(target: PageStatus) {
		// While a revision is shown, the canvas holds its blocks, not the edited ones.
		if (!editor || preview !== null || readOnly) return;
		const { collectPage } = await import('#lib/editor/grapesSetup.js');
		saving = target;
		message = null;
		try {
			message = { kind: 'ok', text: await onsave(target, collectPage(editor)) };
			dirty = false;
			saves += 1;
		} catch (e) {
			// Remote command errors: { status, body: { message } }.
			const failure = e as { status?: number; body?: { message?: string }; message?: string };
			if (failure.status === 401) {
				window.location.href = `/cms-admin/login?redirect=${encodeURIComponent(location.pathname)}`;
				return;
			}
			message = { kind: 'error', text: failure.body?.message ?? failure.message ?? 'Enregistrement impossible.' };
			// Refused over the lock of another administrator: the banner follows at once.
			if (failure.status === 409) void lockSession?.check();
		} finally {
			saving = null;
		}
	}

	/** French message of a failed remote call (expired session: back to the login form). */
	function failureText(e: unknown, fallback: string): string {
		const failure = e as { status?: number; body?: { message?: string }; message?: string };
		if (failure.status === 401) window.location.href = `/cms-admin/login?redirect=${encodeURIComponent(location.pathname)}`;
		return failure.body?.message ?? failure.message ?? fallback;
	}

	function toggleHistory() {
		if (historyOpen) closeHistory();
		else historyOpen = true;
	}

	/** Closes the panel (back to the current version first); the focus goes back to « Historique ». */
	async function closeHistory() {
		endPreview();
		historyOpen = false;
		await tick();
		historyButton?.focus();
	}

	/** Shows a revision in the canvas, read-only; the edited content stays aside, untouched. */
	async function showRevision(summary: RevisionSummary, stored: RevisionSummary) {
		if (!editor || saving !== null || readOnly) return;
		const ticket = ++selection;
		preview = { summary, revision: null, error: null, warning: null, confirming: false };
		const level = me?.level ?? 'charte';
		try {
			const [revision, storedRevision] = await Promise.all([
				getRevision(summary.id),
				// The free values a Charte account may keep are those of the stored content (CharteGuard).
				level === 'charte' && stored.id !== summary.id ? getRevision(stored.id).catch(() => null) : null
			]);
			if (ticket !== selection || !historyOpen || !editor) return;
			previewSession ??= openPreview(editor, templateType);
			previewSession.show(revision.blocks);
			const removedSince = storedRevision ? newDeviations(revision.blocks, storedRevision.blocks) : [];
			preview = { summary, revision, error: null, warning: charteWarning(level, removedSince), confirming: false };
		} catch (e) {
			if (ticket !== selection) return;
			const error =
				(e as { status?: number }).status === 404
					? 'Cette version n’est plus disponible (seules les 50 dernières sont gardées). Fermez puis rouvrez l’historique pour voir la liste à jour.'
					: failureText(e, 'Cette version n’a pas pu être lue.');
			preview = { summary, revision: null, error, warning: null, confirming: false };
		}
	}

	/** Puts the edited content back in the canvas (as it was, unsaved changes and undo history included). */
	function endPreview(): number | null {
		selection += 1;
		const shown = preview?.summary.id ?? null;
		previewSession?.close();
		previewSession = null;
		preview = null;
		return shown;
	}

	function backToCurrent() {
		const shown = endPreview();
		if (shown !== null) void historyPanel?.focusRevision(shown);
	}

	/** Échap in the history panel: back to the current version, or closes the panel. */
	function historyEscape() {
		if (preview) backToCurrent();
		else void closeHistory();
	}

	/** Loads the revision into the editor, not saved (asked twice over unsaved changes); Ctrl+Z undoes it. */
	async function restore() {
		if (!editor || !preview?.revision) return;
		if (dirty && !preview.confirming) {
			preview.confirming = true;
			await tick();
			restoreConfirm?.focus();
			return;
		}
		const { revision, warning } = preview;
		endPreview();
		restoreRevision(editor, revision.blocks, templateType);
		historyOpen = false;
		const saveHint = status === 'published' ? '« Publier les modifications »' : '« Enregistrer en brouillon » ou « Publier »';
		message = {
			kind: warning ? 'error' : 'ok',
			text:
				`${versionLabel(revision.createdAt)} restaurée dans l’éditeur. Elle n’est pas encore enregistrée : ${saveHint} pour la garder, Ctrl+Z pour annuler.` +
				(warning ? ' Avant d’enregistrer, remplacez les valeurs hors charte de cette version par des valeurs de la charte.' : '')
		};
		await tick();
		historyButton?.focus();
	}
</script>

<svelte:head><title>{title} | Builder Clik&amp;Yack</title></svelte:head>

<div class="builder">
	<header class="toolbar yb-topbar">
		<img class="yb-logo" src="/yack/logo-yack.png" alt="Yack" />
		<BackLink href={back.href} label={back.label} data-test-back />
		<div class="title">
			<strong>{title}</strong>
			<code>{subtitle}</code>
		</div>
		<span class="yb-badge yb-badge-{status}" data-test-status>{statusLabel}</span>
		{#if me}
			<span
				class="level level-{me.level}"
				title={me.level === 'charte' ? 'Vos réglages de style restent dans la charte Yack.' : 'Votre compte peut sortir de la charte Yack (couleurs et tailles libres).'}
				data-test-level={me.level}>Niveau : {me.levelLabel || LEVEL_LABELS[me.level]}</span
			>
		{/if}
		{#if dirty}<span class="dirty">Modifications non enregistrées</span>{/if}
		<span class="spacer"></span>
		{#if revisionTarget}
			<button
				type="button"
				class="yb-btn yb-btn-outline history-toggle"
				aria-expanded={historyOpen}
				aria-controls="history-panel"
				disabled={!editor || readOnly}
				bind:this={historyButton}
				onclick={toggleHistory}
				data-test-history>Historique</button
			>
		{/if}
		{@render tools?.()}
		<div class="devices yb-segmented" role="group" aria-label="Aperçu">
			{#each [['desktop', 'Ordinateur'], ['tablet', 'Tablette'], ['mobile', 'Mobile']] as [id, label] (id)}
				<button class:active={device === id} onclick={() => setDevice(id)}>{label}</button>
			{/each}
		</div>
		{#if view}<a class="yb-btn yb-btn-outline" href={view.href} target="_blank">{view.label}</a>{/if}
		{#if status === 'published'}
			<!-- One version per page: saving a draft takes the published page off the site, so it is asked twice. -->
			{#if confirmUnpublish}
				<span class="confirm" role="alert">{templateType ? 'Le template ne s’appliquera plus.' : 'La page ne sera plus en ligne.'}</span>
				<button class="yb-btn yb-btn-dark" disabled={saving !== null || preview !== null || readOnly} onclick={() => { confirmUnpublish = false; save('draft'); }} data-test-unpublish-confirm>
					{templateType ? 'Oui, repasser en brouillon' : 'Oui, retirer du site'}
				</button>
				<button class="yb-btn yb-btn-outline" onclick={() => (confirmUnpublish = false)}>Annuler</button>
			{:else}
				<button class="yb-btn yb-btn-outline" disabled={saving !== null || preview !== null || readOnly} onclick={() => (confirmUnpublish = true)} data-test-unpublish>
					{saving === 'draft' ? 'Retrait…' : templateType ? 'Repasser en brouillon' : 'Retirer du site'}
				</button>
			{/if}
			<button class="yb-btn yb-btn-primary" disabled={saving !== null || preview !== null || readOnly} onclick={() => save('published')} data-test-publish>
				{saving === 'published' ? 'Publication…' : 'Publier les modifications'}
			</button>
		{:else}
			<button class="yb-btn yb-btn-dark" disabled={saving !== null || preview !== null || readOnly} onclick={() => save('draft')}>
				{saving === 'draft' ? 'Enregistrement…' : 'Enregistrer en brouillon'}
			</button>
			<button class="yb-btn yb-btn-primary" disabled={saving !== null || preview !== null || readOnly} onclick={() => save('published')} data-test-publish>
				{saving === 'published' ? 'Publication…' : 'Publier'}
			</button>
		{/if}
	</header>

	{#if message}
		<div class="message {message.kind}" role={message.kind === 'error' ? 'alert' : 'status'}>
			{message.text}
			<button onclick={() => (message = null)} aria-label="Fermer">×</button>
		</div>
	{/if}

	<div class="workspace">
		<aside class="panel left" inert={preview !== null || readOnly}>
			<div class="tabs" role="tablist" aria-label="Panneau latéral">
				<button role="tab" id="tab-blocks" aria-controls="panel-blocks" aria-selected={leftTab === 'blocks'} onclick={() => (leftTab = 'blocks')}>Blocs</button>
				<button role="tab" id="tab-tree" aria-controls="panel-tree" aria-selected={leftTab === 'tree'} onclick={() => (leftTab = 'tree')}>Structure</button>
			</div>
			<div id="panel-blocks" role="tabpanel" aria-labelledby="tab-blocks" hidden={leftTab !== 'blocks'}>
				<div class="palette-head">
					<input
						type="search"
						class="palette-search"
						aria-label="Rechercher un bloc"
						placeholder="Rechercher un bloc"
						autocomplete="off"
						spellcheck="false"
						bind:value={paletteQuery}
						oninput={() => (closedInSearch = [])}
						onkeydown={searchKeydown}
						data-test-palette-search
					/>
					<!-- svelte-ignore a11y_no_static_element_interactions -->
					<span class="palette-help" onmouseenter={() => (helpOpen = true)} onmouseleave={() => (helpOpen = false)}>
						<button
							type="button"
							class="palette-help-toggle"
							aria-label="Aide de la palette"
							aria-describedby="palette-help"
							aria-expanded={helpOpen}
							onclick={() => (helpOpen = !helpOpen)}
							onfocus={() => (helpOpen = true)}
							onblur={() => (helpOpen = false)}
							onkeydown={helpKeydown}>?</button
						>
						<span id="palette-help" role="tooltip" class="palette-help-text" hidden={!helpOpen}>
							Cliquez sur un bloc (ajout en fin de page) ou glissez-le à l'endroit voulu. La mise en forme suit la charte Yack.
						</span>
					</span>
				</div>
				{#if searching && paletteView.length === 0}
					<p class="hint palette-empty" role="status">Aucun bloc ne correspond à « {paletteQuery.trim()} ».</p>
				{/if}
				{#each paletteView as group (group.category)}
					<section class="palette-group" data-test-palette-group={group.category}>
						<h3 class="palette-title">
							<button type="button" class="palette-toggle" aria-expanded={group.open} aria-controls={categoryId(group.category)} onclick={() => togglePaletteGroup(group.category)}>
								{group.category}
							</button>
						</h3>
						<ul id={categoryId(group.category)} class="palette-blocks" aria-label={group.category} hidden={!group.open}>
							{#each group.items as entry (entry.type)}
								<li>
									<button
										type="button"
										class="palette-block"
										title={entry.description}
										draggable={editor !== null}
										disabled={!editor}
										onclick={() => editor && insertPaletteBlock(editor, entry.type)}
										ondragstart={(event) => paletteDragStart(event, entry.type)}
										ondragend={paletteDragEnd}
										data-test-palette-block={entry.type}
									>
										<!-- Drawn in code (#lib/editor/thumbs/thumbs.ts, icons of the registry): no outside content. -->
										<span class="palette-thumb">{@html blockThumb(entry.type) ?? entry.icon}</span>
										<span class="palette-label">{entry.label}</span>
									</button>
								</li>
							{/each}
						</ul>
					</section>
				{/each}
				<SectionPalette {editor} level={me?.level ?? 'charte'} query={paletteQuery} onmessage={(next) => (message = next)} />
			</div>
			<div id="panel-tree" role="tabpanel" aria-labelledby="tab-tree" hidden={leftTab !== 'tree'}>
				<ComponentTree {editor} />
			</div>
		</aside>
		<div class="canvas-area">
			{#if notice}
				<div class="lock-banner" class:lock-warning={lockView.phase === 'unavailable'} role="region" aria-label="Verrou d’édition" data-test-lock-banner={lockView.phase}>
					<div class="lock-info" role="status">
						<strong>{notice.title}</strong>
						<span>{notice.detail}</span>
					</div>
					{#if notice.action}
						<div class="lock-actions">
							{#if takeoverAsk}
								<span class="lock-confirm" id="lock-question">{takeoverQuestion(lockView, lockKind, dirty)}</span>
								<button
									type="button"
									class="yb-btn yb-btn-primary"
									bind:this={takeoverConfirm}
									disabled={takeoverBusy}
									aria-describedby="lock-question"
									onclick={confirmTakeover}
									onkeydown={(event) => event.key === 'Escape' && cancelTakeover()}
									data-test-takeover-confirm>{takeoverBusy ? 'Prise de main…' : 'Oui, prendre la main'}</button
								>
								<button type="button" class="yb-btn yb-btn-outline" disabled={takeoverBusy} onclick={cancelTakeover} onkeydown={(event) => event.key === 'Escape' && cancelTakeover()}>Annuler</button>
							{:else}
								<button
									type="button"
									class="yb-btn {notice.action === 'takeover' ? 'yb-btn-primary' : 'yb-btn-outline'}"
									bind:this={lockAction}
									onclick={notice.action === 'takeover' ? askTakeover : reloadSaved}
									data-test-lock-action={notice.action}>{notice.actionLabel}</button
								>
							{/if}
						</div>
					{/if}
				</div>
			{/if}
			{#if preview}
				<div class="revision-banner" role="region" aria-label="Version affichée" data-test-revision-banner>
					<div class="revision-info" role="status">
						<strong>{versionLabel(preview.summary.createdAt)}</strong>
						<span>
							{authorLabel(preview.summary.author)}, {revisionStatusLabel(preview.summary.status, preview.summary.target)}.
							{#if preview.revision}Lecture seule : restaurer remplace les blocs {templateType ? 'du template (nom et réglages inchangés)' : 'de la page (titre et réglages inchangés)'}, sans enregistrer.{:else if !preview.error}Chargement de la version…{/if}
						</span>
					</div>
					{#if preview.error}<p class="revision-error" role="alert">{preview.error}</p>{/if}
					{#if preview.warning}<p class="revision-warning" data-test-revision-warning>{preview.warning}</p>{/if}
					<div class="revision-actions">
						{#if preview.confirming}
							<span class="revision-confirm" role="alert">Vos modifications non enregistrées seront remplacées par cette version (Ctrl+Z les rétablit).</span>
							<button type="button" class="yb-btn yb-btn-primary" bind:this={restoreConfirm} onclick={restore} data-test-restore-confirm>Oui, restaurer</button>
							<button type="button" class="yb-btn yb-btn-outline" onclick={() => preview && (preview.confirming = false)}>Annuler</button>
						{:else}
							<button type="button" class="yb-btn yb-btn-primary" disabled={!preview.revision} onclick={restore} data-test-restore>Restaurer cette version</button>
							<button type="button" class="yb-btn yb-btn-outline" onclick={backToCurrent} data-test-back-to-current>Revenir à la version en cours</button>
						{/if}
					</div>
				</div>
			{/if}
			<div class="canvas" bind:this={canvasEl}></div>
		</div>
		<aside class="panel right" inert={readOnly}>
			{#if historyOpen && revisionTarget}
				<History
					bind:this={historyPanel}
					target={revisionTarget}
					current={preview?.summary.id ?? null}
					{saves}
					onselect={showRevision}
					onescape={historyEscape}
					onclose={closeHistory}
				/>
			{/if}
			<div class="tabs" role="tablist" aria-label="Réglages" hidden={historyOpen}>
				{#each panels as panel (panel.id)}
					<button role="tab" id="tab-{panel.id}" aria-controls="panel-{panel.id}" aria-selected={rightTab === panel.id} onclick={() => (rightTab = panel.id)}>
						{panel.label}
					</button>
				{/each}
				<button role="tab" id="tab-block" aria-controls="panel-block" aria-selected={rightTab === 'block'} onclick={() => (rightTab = 'block')}>Bloc</button>
			</div>
			{#each panels as panel (panel.id)}
				<div id="panel-{panel.id}" role="tabpanel" aria-labelledby="tab-{panel.id}" hidden={historyOpen || rightTab !== panel.id}>
					{@render panel.body()}
				</div>
			{/each}
			<div id="panel-block" role="tabpanel" aria-labelledby="tab-block" hidden={historyOpen || rightTab !== 'block'}>
				<BlockPanel {editor} />
				<SaveSection {editor} level={me?.level ?? 'charte'} onmessage={(next) => (message = next)} />
			</div>
		</aside>
	</div>
</div>

<style>
	.builder {
		display: flex;
		flex-direction: column;
		height: 100vh;
		overflow: hidden;
	}
	.confirm {
		font-size: 0.8125rem;
		font-weight: 700;
		color: var(--yack-black);
	}
	/* Barre du haut du back-office (.yb-topbar, admin.css) ; filet en bas : les panneaux dessous sont blancs eux aussi. */
	.toolbar {
		flex-wrap: wrap;
		gap: 0.5rem 0.75rem;
		border-bottom: 1px solid var(--yack-divider);
	}
	.title {
		display: flex;
		flex-direction: column;
		min-width: 0;
		max-width: 14rem;
		line-height: 1.2;
		font-size: 1rem;
	}
	.title code {
		font-size: 0.75rem;
		color: var(--yack-gray-ds);
	}
	.dirty {
		font-size: 0.75rem;
		font-weight: 700;
		color: var(--yack-magenta);
		white-space: nowrap;
	}
	.spacer {
		flex: 1;
	}
	.devices {
		font-size: 0.8125rem;
	}
	.message {
		display: flex;
		justify-content: space-between;
		padding: 0.6rem 1rem;
		font-size: 0.875rem;
		font-weight: 700;
		color: var(--yack-black);
	}
	.message.ok {
		background: var(--yack-teal);
	}
	.message.error {
		background: var(--yack-orange);
	}
	.message button {
		background: none;
		border: 0;
		font-size: 1.1rem;
		cursor: pointer;
		color: inherit;
	}
	.workspace {
		flex: 1;
		display: grid;
		grid-template-columns: 290px 1fr 340px;
		min-height: 0;
	}
	.panel {
		overflow-y: auto;
		padding: 1rem;
		background: var(--yack-white);
		border-right: 1px solid var(--yack-divider);
	}
	.panel.right {
		border-right: 0;
		border-left: 1px solid var(--yack-divider);
	}
	/* Also the caller's panels (snippets). */
	.panel :global(h2) {
		margin: 0 0 0.75rem;
		font-size: 1rem;
		font-weight: 700;
		line-height: 1.5rem;
		color: var(--yack-black);
	}
	.panel :global(h2:not(:first-child)) {
		margin-top: 1.5rem;
	}
	.hint,
	.panel :global(.hint) {
		font-size: 0.8rem;
		color: var(--yack-gray);
		margin: 0 0 1rem;
	}
	.tabs {
		display: flex;
		gap: 0.25rem;
		margin: 0 0 0.9rem;
		border-bottom: 1px solid var(--yack-divider);
	}
	.tabs button {
		flex: 1;
		padding: 0.5rem 0.5rem;
		font: inherit;
		font-size: 0.875rem;
		font-weight: 700;
		color: var(--yack-gray-ds);
		background: none;
		border: 0;
		border-bottom: 2px solid transparent;
		margin-bottom: -1px;
		cursor: pointer;
	}
	.tabs button[aria-selected='true'] {
		color: var(--yack-magenta);
		border-bottom-color: var(--yack-magenta);
	}
	.canvas-area {
		display: flex;
		flex-direction: column;
		min-height: 0;
		min-width: 0;
	}
	.canvas {
		flex: 1;
		min-height: 0;
	}
	/* Revision shown in the canvas (D11): light banner, buttons of admin.css. */
	.revision-banner {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 0.5rem 1rem;
		padding: 0.6rem 1rem;
		color: var(--yack-black);
		background: var(--yack-magenta-10-solid);
		border-bottom: 1px solid var(--yack-gray-25);
		font-size: 0.8125rem;
	}
	.revision-info {
		display: flex;
		flex-direction: column;
		gap: 0.1rem;
		flex: 1 1 18rem;
	}
	.revision-info strong {
		font-size: 0.95rem;
	}
	.revision-error,
	.revision-warning {
		flex-basis: 100%;
		margin: 0;
		color: var(--yack-magenta-dark);
		font-weight: 700;
	}
	.revision-actions {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 0.5rem;
	}
	.revision-confirm {
		font-weight: 600;
	}
	.revision-banner :global(.yb-btn:focus-visible),
	.history-toggle:focus-visible {
		outline: 2px solid var(--yack-magenta);
		outline-offset: 2px;
	}
	/* Edit lock (D13): light banner over the canvas, no coloured bar; buttons of admin.css. */
	.lock-banner {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 0.5rem 1rem;
		padding: 0.6rem 1rem;
		color: var(--yack-black);
		background: var(--yack-yellow);
		border-bottom: 1px solid var(--yack-gray-25);
		font-size: 0.8125rem;
	}
	.lock-info {
		display: flex;
		flex-direction: column;
		gap: 0.1rem;
		flex: 1 1 18rem;
	}
	.lock-info strong {
		font-size: 0.95rem;
	}
	.lock-warning .lock-info strong {
		color: var(--yack-black);
	}
	.lock-actions {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 0.5rem;
	}
	.lock-confirm {
		font-weight: 600;
		flex-basis: 100%;
	}
	.lock-banner :global(.yb-btn:focus-visible) {
		outline: 2px solid var(--yack-magenta);
		outline-offset: 2px;
	}
	/* While the history is open, its panel replaces the tabs; while a revision is shown, the palette and the tree wait. */
	.tabs[hidden] {
		display: none;
	}
	.panel[inert] {
		opacity: 0.5;
	}
	.history-toggle[aria-expanded='true'] {
		background: var(--yack-magenta);
		border-color: var(--yack-magenta);
		color: var(--yack-white);
	}

	/* GrapesJS chrome restyled (light, Yack accents) */
	.builder :global(.gjs-one-bg) {
		background: var(--yack-white);
	}
	.builder :global(.gjs-two-color) {
		color: var(--yack-dark);
	}
	.builder :global(.gjs-four-color),
	.builder :global(.gjs-four-color-h:hover) {
		color: var(--yack-magenta);
	}
	.builder :global(.gjs-cv-canvas) {
		width: 100%;
		height: 100%;
		top: 0;
		background: var(--yack-light);
	}
	/* Palette (E6): search and « ? » on one line, rubriques that fold, blocks in tiles of two columns with their
	   thumbnail (two greys of the text colour). The accent only borders and colours, it never fills (E2). */
	.palette-head {
		position: relative;
		display: flex;
		align-items: center;
		gap: 0.5rem;
		margin: 0 0 0.5rem;
	}
	/* Field of the panels (pill, halo on focus: below), as wide as the line allows. */
	.palette-search {
		flex: 1;
		min-width: 0;
	}
	.palette-help {
		display: inline-flex;
	}
	.palette-help-toggle {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 1.5rem;
		height: 1.5rem;
		padding: 0;
		font: inherit;
		font-size: 0.75rem;
		font-weight: 700;
		color: var(--yack-gray);
		background: var(--yack-white);
		border: 1px solid var(--yack-border-strong);
		border-radius: var(--yack-radius-circle);
		cursor: help;
	}
	.palette-help-toggle:hover,
	.palette-help-toggle[aria-expanded='true'] {
		color: var(--yack-magenta);
		border-color: var(--yack-magenta);
	}
	.palette-help-text {
		position: absolute;
		top: calc(100% + 0.25rem);
		right: 0;
		left: 0;
		z-index: 2;
		padding: 0.5rem 0.75rem;
		font-size: 0.75rem;
		line-height: 1.4;
		color: var(--yack-white);
		background: var(--yack-dark);
		border-radius: var(--yack-radius-xs);
		box-shadow: 0 2px 8px var(--yack-shadow-soft);
	}
	.palette-help-text[hidden] {
		display: none;
	}
	.palette-empty {
		margin: 0.5rem 0;
	}
	.palette-title {
		margin: 0;
	}
	.palette-toggle {
		display: flex;
		align-items: center;
		gap: 0.375rem;
		width: 100%;
		padding: 0.5rem 0 0.375rem;
		font: inherit;
		font-size: 0.75rem;
		font-weight: 700;
		text-align: left;
		color: var(--yack-gray);
		background: none;
		border: 0;
		cursor: pointer;
	}
	/* Chevron drawn by the borders: right when folded, down when open. */
	.palette-toggle::before {
		content: '';
		width: 0.375rem;
		height: 0.375rem;
		border-right: 1.5px solid currentColor;
		border-bottom: 1.5px solid currentColor;
		transform: rotate(-45deg);
		transition: transform 0.15s;
	}
	.palette-toggle[aria-expanded='true']::before {
		transform: rotate(45deg);
	}
	.palette-toggle:hover {
		color: var(--yack-black);
	}
	.palette-blocks {
		list-style: none;
		margin: 0 0 0.25rem;
		padding: 0;
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 0.5rem;
	}
	.palette-blocks[hidden] {
		display: none;
	}
	.palette-block {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 0.25rem;
		width: 100%;
		height: 100%;
		padding: 0.5rem 0.25rem;
		font: inherit;
		font-size: 0.75rem;
		line-height: 1.25;
		text-align: center;
		color: var(--yack-dark);
		background: var(--yack-white);
		border: 1px solid var(--yack-border);
		border-radius: var(--yack-radius-xs);
		cursor: grab;
	}
	.palette-block:hover {
		border-color: var(--yack-magenta);
	}
	.palette-block:disabled {
		cursor: default;
		opacity: 0.6;
	}
	.palette-thumb {
		display: block;
		width: 64px;
		height: 40px;
		color: var(--yack-gray);
	}
	/* Icons of the registry (block without thumbnail): line drawing, centred. */
	.palette-thumb :global(svg) {
		display: block;
		width: 100%;
		height: 100%;
	}
	.palette-label {
		overflow-wrap: anywhere;
	}
	.palette-help-toggle:focus-visible,
	.palette-toggle:focus-visible,
	.palette-block:focus-visible {
		outline: 2px solid var(--yack-magenta);
		outline-offset: 2px;
	}
	.level {
		box-sizing: border-box;
		display: inline-flex;
		align-items: center;
		min-height: 1.5rem;
		padding: 0.3125rem 0.75rem;
		border-radius: var(--yack-radius-card);
		background: var(--yack-magenta-10);
		color: var(--yack-magenta);
		font-size: 0.75rem;
		font-weight: 700;
		line-height: 0.875rem;
		white-space: nowrap;
	}
	.level-libre,
	.level-libre_avertissement {
		background: var(--yack-magenta);
		color: var(--yack-white);
	}
	.toolbar :global(.yb-btn) {
		min-height: var(--yb-control-dense);
		padding: 0.25rem 1rem;
	}
	.tabs button:hover {
		color: var(--yack-black);
	}
	.lock-warning {
		background: var(--yack-orange);
	}
	div.builder {
		/* Variables de GrapesJS : sélection, survol et pastille du nom du bloc en magenta (bleu par défaut), police Lato. */
		--gjs-color-blue: var(--yack-magenta);
		--gjs-color-highlight: var(--yack-magenta-50);
		--gjs-color-yellow: var(--yack-teal);
		--gjs-main-font: var(--yack-font);
	}
	/* Champs de tous les panneaux (réglages de la page, traits Yack) : pilule magenta 10 %, liseré et halo magenta au focus. */
	.panel :global(input:not([type='checkbox']):not([type='radio']):not([type='color']):not([type='file']):not([type='range']):not([type='hidden'])),
	.panel :global(select),
	.panel :global(textarea) {
		box-sizing: border-box;
		min-height: var(--yb-control);
		padding: 0.4375rem 1.25rem;
		border: 1px solid transparent;
		border-radius: var(--yack-radius-pill);
		background-color: var(--yack-magenta-10-solid);
		color: var(--yack-black);
		font-size: 0.875rem;
	}
	.panel :global(textarea) {
		border-radius: var(--yack-radius-card);
	}
	.panel :global(select) {
		appearance: none;
		padding-right: 2.5rem;
		background-image: var(--yb-chevron);
		background-repeat: no-repeat;
		background-position: right 1rem center;
		background-size: 1rem;
	}
	.panel :global(input::placeholder),
	.panel :global(textarea::placeholder) {
		color: var(--yack-magenta);
		opacity: 0.85;
	}
	.panel :global(input:not([type='checkbox']):not([type='radio']):not([type='color']):not([type='file']):not([type='range']):not([type='hidden']):focus),
	.panel :global(select:focus),
	.panel :global(textarea:focus) {
		outline: 0;
		border-color: var(--yack-magenta);
		box-shadow: 0 0 0 0.2rem var(--yack-magenta-20);
	}
	.title strong,
	.title code {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	.toolbar :global(.yb-segmented button) {
		padding-inline: 0.75rem;
	}
	.toolbar :global(.yb-badge) {
		white-space: nowrap;
	}
</style>
