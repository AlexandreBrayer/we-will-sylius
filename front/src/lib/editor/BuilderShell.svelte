<script lang="ts" module>
	export interface ShellPanel {
		id: string;
		label: string;
		body: Snippet;
	}
</script>

<script lang="ts">
	// GrapesJS editor of the builder, shared by CMS pages and templates: toolbar (devices, view,
	// draft / publish), left panel "Blocs | Structure" (palette, component tree), canvas, right panel
	// with the caller's panels (page / template settings, assignments…) and the selected block's props.
	import 'grapesjs/dist/css/grapes.min.css';
	import { onMount, type Snippet } from 'svelte';
	import type { Editor } from 'grapesjs';
	import type { YackContext } from '#lib/blocks/context.js';
	import type { PageBlock, PageStatus, TemplateType } from '#lib/blocks/types.js';
	import ComponentTree from '#lib/editor/tree/ComponentTree.svelte';
	import { installTree } from '#lib/editor/tree/componentTree.js';

	type Content = { blocks: PageBlock[]; grapesData: Record<string, unknown> | null };

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
		/** Saves the content with `status`; resolves with the confirmation shown, rejects with the error. */
		onsave: (status: PageStatus, collected: { blocks: PageBlock[]; grapesData: Record<string, unknown> }) => Promise<string>;
		editor?: Editor | null;
		dirty?: boolean;
	} = $props();

	let leftTab = $state<'blocks' | 'tree'>('blocks');
	// svelte-ignore state_referenced_locally
	let rightTab = $state(panels[0]?.id ?? 'block');
	let canvasEl: HTMLDivElement;
	let blocksEl: HTMLDivElement;
	let traitsEl: HTMLDivElement;
	let device = $state('desktop');
	let saving = $state<PageStatus | null>(null);
	/** Published content: "Retirer du site" asks a confirmation in the toolbar before going back to draft. */
	let confirmUnpublish = $state(false);
	let message = $state<{ kind: 'ok' | 'error'; text: string } | null>(null);

	onMount(() => {
		let destroyed = false;
		let uninstallTree: (() => void) | undefined;
		import('#lib/editor/grapesSetup.js').then(({ initEditor }) => {
			if (destroyed) return;
			const instance = initEditor({ container: canvasEl, blocksEl, traitsEl, content, templateType, context });
			uninstallTree = installTree(instance);
			instance.on('load', () => {
				instance.on('update', () => (dirty = true));
				instance.on('component:selected', () => (rightTab = 'block'));
			});
			editor = instance;
		});
		const beforeUnload = (event: BeforeUnloadEvent) => {
			if (dirty) event.preventDefault();
		};
		window.addEventListener('beforeunload', beforeUnload);
		return () => {
			destroyed = true;
			window.removeEventListener('beforeunload', beforeUnload);
			uninstallTree?.();
			editor?.destroy();
			editor = null;
		};
	});

	function setDevice(id: string) {
		device = id;
		editor?.setDevice(id);
	}

	async function save(target: PageStatus) {
		if (!editor) return;
		const { collectPage } = await import('#lib/editor/grapesSetup.js');
		saving = target;
		message = null;
		try {
			message = { kind: 'ok', text: await onsave(target, collectPage(editor)) };
			dirty = false;
		} catch (e) {
			// Remote command errors: { status, body: { message } }.
			const failure = e as { status?: number; body?: { message?: string }; message?: string };
			if (failure.status === 401) {
				window.location.href = `/cms-admin/login?redirect=${encodeURIComponent(location.pathname)}`;
				return;
			}
			message = { kind: 'error', text: failure.body?.message ?? failure.message ?? 'Enregistrement impossible.' };
		} finally {
			saving = null;
		}
	}
</script>

<svelte:head><title>{title} — Builder Clik&amp;Yack</title></svelte:head>

<div class="builder">
	<header class="toolbar">
		<a href={back.href} class="back" title={back.label}>←</a>
		<img class="yb-logo" src="/yack/logo-yack.png" alt="Yack" />
		<div class="title">
			<strong>{title}</strong>
			<code>{subtitle}</code>
		</div>
		<span class="yb-badge yb-badge-{status}" data-test-status>{statusLabel}</span>
		{#if dirty}<span class="dirty">Modifications non enregistrées</span>{/if}
		<span class="spacer"></span>
		<div class="devices" role="group" aria-label="Aperçu">
			{#each [['desktop', 'Ordinateur'], ['tablet', 'Tablette'], ['mobile', 'Mobile']] as [id, label] (id)}
				<button class:active={device === id} onclick={() => setDevice(id)}>{label}</button>
			{/each}
		</div>
		{#if view}<a class="yb-btn yb-btn-outline" href={view.href} target="_blank">{view.label}</a>{/if}
		{#if status === 'published'}
			<!-- One version per page: saving a draft takes the published page off the site, so it is asked twice. -->
			{#if confirmUnpublish}
				<span class="confirm" role="alert">{templateType ? 'Le template ne s’appliquera plus.' : 'La page ne sera plus en ligne.'}</span>
				<button class="yb-btn yb-btn-dark" disabled={saving !== null} onclick={() => { confirmUnpublish = false; save('draft'); }} data-test-unpublish-confirm>
					{templateType ? 'Oui, repasser en brouillon' : 'Oui, retirer du site'}
				</button>
				<button class="yb-btn yb-btn-outline" onclick={() => (confirmUnpublish = false)}>Annuler</button>
			{:else}
				<button class="yb-btn yb-btn-outline" disabled={saving !== null} onclick={() => (confirmUnpublish = true)} data-test-unpublish>
					{saving === 'draft' ? 'Retrait…' : templateType ? 'Repasser en brouillon' : 'Retirer du site'}
				</button>
			{/if}
			<button class="yb-btn yb-btn-primary" disabled={saving !== null} onclick={() => save('published')} data-test-publish>
				{saving === 'published' ? 'Publication…' : 'Publier les modifications'}
			</button>
		{:else}
			<button class="yb-btn yb-btn-dark" disabled={saving !== null} onclick={() => save('draft')}>
				{saving === 'draft' ? 'Enregistrement…' : 'Enregistrer en brouillon'}
			</button>
			<button class="yb-btn yb-btn-primary" disabled={saving !== null} onclick={() => save('published')} data-test-publish>
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
		<aside class="panel left">
			<div class="tabs" role="tablist" aria-label="Panneau latéral">
				<button role="tab" id="tab-blocks" aria-controls="panel-blocks" aria-selected={leftTab === 'blocks'} onclick={() => (leftTab = 'blocks')}>Blocs</button>
				<button role="tab" id="tab-tree" aria-controls="panel-tree" aria-selected={leftTab === 'tree'} onclick={() => (leftTab = 'tree')}>Structure</button>
			</div>
			<div id="panel-blocks" role="tabpanel" aria-labelledby="tab-blocks" hidden={leftTab !== 'blocks'}>
				<p class="hint">Cliquez sur un bloc (ajout en fin de page) ou glissez-le à l'endroit voulu. La mise en forme suit la charte Yack.</p>
				<div bind:this={blocksEl}></div>
			</div>
			<div id="panel-tree" role="tabpanel" aria-labelledby="tab-tree" hidden={leftTab !== 'tree'}>
				<ComponentTree {editor} />
			</div>
		</aside>
		<div class="canvas" bind:this={canvasEl}></div>
		<aside class="panel right">
			<div class="tabs" role="tablist" aria-label="Réglages">
				{#each panels as panel (panel.id)}
					<button role="tab" id="tab-{panel.id}" aria-controls="panel-{panel.id}" aria-selected={rightTab === panel.id} onclick={() => (rightTab = panel.id)}>
						{panel.label}
					</button>
				{/each}
				<button role="tab" id="tab-block" aria-controls="panel-block" aria-selected={rightTab === 'block'} onclick={() => (rightTab = 'block')}>Bloc</button>
			</div>
			{#each panels as panel (panel.id)}
				<div id="panel-{panel.id}" role="tabpanel" aria-labelledby="tab-{panel.id}" hidden={rightTab !== panel.id}>
					{@render panel.body()}
				</div>
			{/each}
			<div id="panel-block" role="tabpanel" aria-labelledby="tab-block" hidden={rightTab !== 'block'}>
				<h2>Bloc sélectionné</h2>
				<div class="traits" bind:this={traitsEl}></div>
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
		font-size: 0.85rem;
		color: #fff;
	}
	.toolbar {
		display: flex;
		align-items: center;
		gap: 0.75rem;
		padding: 0.5rem 1rem;
		background: var(--yack-dark);
		color: #fff;
	}
	.toolbar .yb-logo {
		height: 32px;
		background: #fff;
		border-radius: 0.25rem;
		padding: 2px;
	}
	.back {
		color: #fff;
		text-decoration: none;
		font-size: 1.3rem;
	}
	.title {
		display: flex;
		flex-direction: column;
		line-height: 1.2;
	}
	.title code {
		font-size: 0.75rem;
		opacity: 0.7;
	}
	.dirty {
		font-size: 0.8rem;
		color: #f3b6dc;
	}
	.spacer {
		flex: 1;
	}
	.devices {
		display: flex;
		border: 1px solid rgba(255, 255, 255, 0.3);
		border-radius: 0.375rem;
		overflow: hidden;
	}
	.devices button {
		background: transparent;
		color: #fff;
		border: 0;
		padding: 0.4rem 0.7rem;
		font-size: 0.8rem;
		cursor: pointer;
	}
	.devices button.active {
		background: var(--yack-magenta);
	}
	.message {
		display: flex;
		justify-content: space-between;
		padding: 0.6rem 1rem;
		font-size: 0.9rem;
	}
	.message.ok {
		background: #d1f2df;
		color: #0f6b39;
	}
	.message.error {
		background: #fbe9ef;
		color: #8a1538;
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
		background: #fff;
		border-right: 1px solid var(--yack-border);
	}
	.panel.right {
		border-right: 0;
		border-left: 1px solid var(--yack-border);
	}
	/* Also the caller's panels (snippets). */
	.panel :global(h2) {
		margin: 0 0 0.75rem;
		font-size: 0.8rem;
		text-transform: uppercase;
		letter-spacing: 0.05em;
		color: var(--yack-magenta);
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
		border-bottom: 1px solid var(--yack-border);
	}
	.tabs button {
		flex: 1;
		padding: 0.45rem 0.5rem;
		font: inherit;
		font-size: 0.8rem;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.05em;
		color: var(--yack-gray);
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
	.canvas {
		min-height: 0;
	}

	/* GrapesJS chrome restyled (light, Yack accents) */
	.builder :global(.gjs-one-bg) {
		background: #fff;
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
		background: #ececf0;
	}
	.builder :global(.gjs-block-category .gjs-title) {
		font-size: 0.75rem;
		text-transform: uppercase;
		letter-spacing: 0.05em;
		color: var(--yack-gray);
		background: none;
		border: 0;
		padding: 0.6rem 0 0.4rem;
	}
	.builder :global(.gjs-block-category) {
		border: 0;
	}
	.builder :global(.gjs-blocks-c) {
		display: grid;
		gap: 0.5rem;
	}
	.builder :global(.gjs-block) {
		width: auto;
		min-height: 0;
		margin: 0;
		padding: 0.6rem;
		display: grid;
		grid-template-columns: 32px 1fr;
		gap: 0.6rem;
		align-items: center;
		text-align: left;
		border: 1px solid var(--yack-border);
		border-radius: 0.5rem;
		box-shadow: none;
		color: var(--yack-dark);
	}
	.builder :global(.gjs-block:hover) {
		border-color: var(--yack-magenta);
		color: var(--yack-magenta);
	}
	.builder :global(.gjs-block svg) {
		width: 32px;
		height: 32px;
		fill: none;
	}
	.builder :global(.gjs-block-label) {
		display: flex;
		flex-direction: column;
		font-size: 0.85rem;
	}
	.builder :global(.gjs-block-label small) {
		font-size: 0.72rem;
		color: var(--yack-gray);
		font-weight: 400;
	}
	.traits :global(.gjs-trt-trait) {
		display: flex;
		flex-direction: column;
		align-items: stretch;
		padding: 0 0 0.8rem;
	}
	.traits :global(.gjs-label-wrp) {
		width: auto;
		min-width: 0;
		padding-bottom: 0.3rem;
	}
	.traits :global(.gjs-label) {
		font-size: 0.8rem;
		font-weight: 600;
		color: var(--yack-dark);
	}
	.traits :global(.gjs-field) {
		background: #fff;
		border: 1px solid var(--yack-border);
		border-radius: 0.375rem;
		color: var(--yack-dark);
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
	.traits :global(.gjs-trt-header) {
		display: none;
	}
</style>
