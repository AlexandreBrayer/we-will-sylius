<script lang="ts">
	// Published CMS page (?apercu: draft preview for an administrator logged into the builder).
	import { page as appPage } from '$app/state';
	import CmsPageView from '#lib/blocks/CmsPageView.svelte';
	import { getCmsPage } from '#lib/remote/cms.remote.js';
	import { getViewer } from '#lib/remote/session.remote.js';

	let { params } = $props();

	const data = $derived(await getCmsPage({ slug: params.slug, preview: appPage.url.searchParams.has('apercu') }));
	const page = $derived(data.page);
</script>

<svelte:head>
	<title>{page.metaTitle || `${page.title} | Clik&Yack`}</title>
	{#if page.metaDescription}<meta name="description" content={page.metaDescription} />{/if}
	{#if data.preview}<meta name="robots" content="noindex" />{/if}
</svelte:head>

<CmsPageView {page} preview={data.preview} catalog={data.catalog} viewer={await getViewer()} />
