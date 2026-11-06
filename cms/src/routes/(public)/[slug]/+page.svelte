<script lang="ts">
	import BlockRenderer from '$lib/blocks/BlockRenderer.svelte';
	import { setYackContext } from '$lib/blocks/context.js';

	let { data } = $props();
	// svelte-ignore state_referenced_locally
	setYackContext({ syliusUrl: data.syliusUrl, catalog: data.catalog, editor: false });
	const page = $derived(data.page);
</script>

<svelte:head>
	<title>{page.metaTitle || `${page.title} | Clik&Yack`}</title>
	{#if page.metaDescription}<meta name="description" content={page.metaDescription} />{/if}
	{#if data.preview}<meta name="robots" content="noindex" />{/if}
</svelte:head>

{#if data.preview}
	<div class="alert alert-warning rounded-0 mb-0 text-center" role="status">
		Aperçu {page.status === 'draft' ? 'du brouillon' : 'de la page'} — visible uniquement par les administrateurs connectés au builder.
	</div>
{/if}

<div class="yack-cms-page" data-test-yack-cms-page={page.slug}>
	{#each page.blocks as block, index (index)}
		<BlockRenderer {block} />
	{/each}
</div>
