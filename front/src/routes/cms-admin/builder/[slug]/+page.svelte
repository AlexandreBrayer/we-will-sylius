<script lang="ts">
	import 'grapesjs/dist/css/grapes.min.css';
	import { onMount } from 'svelte';
	import type { Editor } from 'grapesjs';
	import type { CmsPage, PageStatus } from '#lib/blocks/types.js';
	import ComponentTree from '#lib/editor/tree/ComponentTree.svelte';
	import { installTree } from '#lib/editor/tree/componentTree.js';

	let { data } = $props();

	// svelte-ignore state_referenced_locally
	let page = $state<CmsPage>(structuredClone(data.page));
	let editor = $state.raw<Editor | null>(null);
	let leftTab = $state<'blocks' | 'tree'>('blocks');
	let canvasEl: HTMLDivElement;
	let blocksEl: HTMLDivElement;
	let traitsEl: HTMLDivElement;
	let device = $state('desktop');
	let saving = $state<PageStatus | null>(null);
	let dirty = $state(false);
	let message = $state<{ kind: 'ok' | 'error'; text: string } | null>(null);

	onMount(() => {
		let destroyed = false;
		let uninstallTree: (() => void) | undefined;
		import('#lib/editor/grapesSetup.js').then(({ initEditor }) => {
			if (destroyed) return;
			const instance = initEditor({ container: canvasEl, blocksEl, traitsEl, page });
			uninstallTree = installTree(instance);
			instance.on('load', () => {
				instance.on('update', () => (dirty = true));
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
		};
	});

	function setDevice(id: string) {
		device = id;
		editor?.setDevice(id);
	}

	async function save(status: PageStatus) {
		if (!editor) return;
		const { collectPage } = await import('#lib/editor/grapesSetup.js');
		saving = status;
		message = null;
		try {
			const response = await fetch(`/cms-admin/api/pages/${page.slug}`, {
				method: 'PUT',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ ...page, ...collectPage(editor), status })
			});
			const body = await response.json();
			if (response.status === 401) {
				window.location.href = `/cms-admin/login?redirect=${encodeURIComponent(location.pathname)}`;
				return;
			}
			if (!response.ok) throw new Error(body.message ?? `Erreur ${response.status}`);
			page.status = body.status;
			page.updatedAt = body.updatedAt;
			dirty = false;
			message = { kind: 'ok', text: status === 'published' ? 'Page publiée.' : 'Brouillon enregistré.' };
		} catch (error) {
			message = { kind: 'error', text: (error as Error).message };
		} finally {
			saving = null;
		}
	}

	const publicUrl = $derived(`/${page.slug === 'accueil' ? '' : page.slug}${page.status === 'published' ? '' : '?apercu=1'}`);
</script>

<svelte:head><title>{page.title} — Builder Clik&amp;Yack</title></svelte:head>

<div class="builder">
	<header class="toolbar">
		<a href="/cms-admin/builder" class="back" title="Toutes les pages">←</a>
		<img class="yb-logo" src="/yack/logo-yack.png" alt="Yack" />
		<div class="title">
			<strong>{page.title}</strong>
			<code>/{page.slug}</code>
		</div>
		<span class="yb-badge yb-badge-{page.status}" data-test-status>{page.status === 'published' ? 'Publiée' : 'Brouillon'}</span>
		{#if dirty}<span class="dirty">Modifications non enregistrées</span>{/if}
		<span class="spacer"></span>
		<div class="devices" role="group" aria-label="Aperçu">
			{#each [['desktop', 'Ordinateur'], ['tablet', 'Tablette'], ['mobile', 'Mobile']] as [id, label] (id)}
				<button class:active={device === id} onclick={() => setDevice(id)}>{label}</button>
			{/each}
		</div>
		<a class="yb-btn yb-btn-outline" href={publicUrl} target="_blank">{page.status === 'published' ? 'Voir la page' : 'Aperçu'}</a>
		<button class="yb-btn yb-btn-dark" disabled={saving !== null} onclick={() => save('draft')}>
			{saving === 'draft' ? 'Enregistrement…' : 'Enregistrer en brouillon'}
		</button>
		<button class="yb-btn yb-btn-primary" disabled={saving !== null} onclick={() => save('published')}>
			{saving === 'published' ? 'Publication…' : 'Publier'}
		</button>
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
			<h2>Page</h2>
			<label class="yb-field">Titre <input bind:value={page.title} oninput={() => (dirty = true)} /></label>
			<label class="yb-field">Meta title <input bind:value={page.metaTitle} oninput={() => (dirty = true)} placeholder="{page.title} | Clik&Yack" /></label>
			<label class="yb-field">Meta description <textarea rows="3" bind:value={page.metaDescription} oninput={() => (dirty = true)}></textarea></label>
			<h2>Bloc sélectionné</h2>
			<div class="traits" bind:this={traitsEl}></div>
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
		grid-template-columns: 290px 1fr 320px;
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
	.panel h2 {
		margin: 0 0 0.75rem;
		font-size: 0.8rem;
		text-transform: uppercase;
		letter-spacing: 0.05em;
		color: var(--yack-magenta);
	}
	.panel h2:not(:first-child) {
		margin-top: 1.5rem;
	}
	.hint {
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
		display: none;
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
