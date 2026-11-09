<script lang="ts">
	import { page as appPage } from '$app/state';
	import type { Viewer } from '#lib/types.js';
	import BlockRenderer from './BlockRenderer.svelte';
	import { setYackContext } from './context.js';
	import type { Catalog, CmsPage } from './types.js';

	let { page, preview, catalog, viewer }: { page: CmsPage; preview: boolean; catalog: Catalog; viewer: Viewer } = $props();

	// Getters: the context follows the props when navigating from a CMS page to another.
	setYackContext({
		get viewer() {
			return viewer;
		},
		get catalog() {
			return catalog;
		},
		editor: false,
		// Login and sign-up links of the blocks come back here (links.ts).
		get returnPath() {
			return appPage.url.pathname + appPage.url.search;
		}
	});
</script>

{#if preview}
	<div class="alert alert-warning rounded-0 mb-0 text-center" role="status">
		Aperçu {page.status === 'draft' ? 'du brouillon' : 'de la page'} : visible uniquement par les administrateurs connectés au builder.
	</div>
{/if}

<div class="yack-cms-page" data-test-yack-cms-page={page.slug}>
	{#each page.blocks as block, index (index)}
		<BlockRenderer {block} />
	{/each}
</div>
