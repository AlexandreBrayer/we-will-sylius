<script lang="ts">
	import CmsPageView from '#lib/blocks/CmsPageView.svelte';

	let { data } = $props();
	const page = $derived(data.page);
</script>

<svelte:head>
	<title>{page.metaTitle || `${page.title} | Clik&Yack`}</title>
	{#if page.metaDescription}<meta name="description" content={page.metaDescription} />{/if}
	{#if data.preview}<meta name="robots" content="noindex" />{/if}
</svelte:head>

<CmsPageView {page} preview={data.preview} catalog={data.catalog} viewer={data.viewer} />
