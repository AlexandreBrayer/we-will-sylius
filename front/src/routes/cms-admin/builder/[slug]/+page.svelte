<script lang="ts">
	// CMS page editor: free blocks, page fields (title, meta), draft / publish (savePage).
	import type { YackContext } from '#lib/blocks/context.js';
	import type { CmsPage } from '#lib/blocks/types.js';
	import BuilderShell from '#lib/editor/BuilderShell.svelte';
	import { editorLoaders } from '#lib/editor/loaders.js';
	import { getPage, savePage } from '#lib/remote/builder.remote.js';
	import { VISITOR } from '#lib/types.js';

	let { params } = $props();

	const loaded = $derived(await getPage(params.slug));
	// Edited copy (fields bound below); the editor reads the blocks once.
	// svelte-ignore state_referenced_locally
	let page = $state<CmsPage>(structuredClone(loaded));
	let dirty = $state(false);
	$effect.pre(() => {
		if (loaded.slug !== page.slug) page = structuredClone(loaded);
	});

	const context: YackContext = { viewer: VISITOR, catalog: null, editor: true, loaders: editorLoaders(() => VISITOR.profile) };

	const publicUrl = $derived(`/${page.slug === 'accueil' ? '' : page.slug}${page.status === 'published' ? '' : '?apercu=1'}`);

	async function save(status: CmsPage['status'], collected: Pick<CmsPage, 'blocks' | 'grapesData'>): Promise<string> {
		const saved = await savePage({
			slug: page.slug,
			title: page.title,
			metaTitle: page.metaTitle || null,
			metaDescription: page.metaDescription || null,
			status,
			...collected
		});
		page.status = saved.status;
		page.updatedAt = saved.updatedAt;
		return status === 'published' ? 'Page publiée.' : 'Brouillon enregistré.';
	}
</script>

{#snippet settings()}
	<label class="yb-field">Titre <input bind:value={page.title} oninput={() => (dirty = true)} /></label>
	<label class="yb-field">Meta title <input bind:value={page.metaTitle} oninput={() => (dirty = true)} placeholder="{page.title} | Clik&Yack" /></label>
	<label class="yb-field">Meta description <textarea rows="3" bind:value={page.metaDescription} oninput={() => (dirty = true)}></textarea></label>
{/snippet}

{#key loaded.slug}
	<BuilderShell
		title={page.title}
		subtitle="/{page.slug}"
		status={page.status}
		statusLabel={page.status === 'published' ? 'Publiée' : 'Brouillon'}
		back={{ href: '/cms-admin/builder', label: 'Toutes les pages' }}
		view={{ href: publicUrl, label: page.status === 'published' ? 'Voir la page' : 'Aperçu' }}
		content={loaded}
		templateType={null}
		{context}
		panels={[{ id: 'page', label: 'Page', body: settings }]}
		onsave={save}
		bind:dirty
	/>
{/key}
