<script lang="ts">
	// Published CMS page (?apercu: draft preview for an administrator logged into the builder).
	import { page as appPage } from '$app/state';
	import CmsPageView from '#lib/blocks/CmsPageView.svelte';
	import { getCmsPage } from '#lib/remote/cms.remote.js';
	import { getViewer } from '#lib/remote/session.remote.js';

	let { params } = $props();

	// Page asked. While the visitor leaves for another route (/inscription, /produit/…), this page stays on screen
	// until the next one is ready, and `params` already holds the params of that route: no `slug` (/inscription), or
	// the slug of another kind of page (/produit/[slug]). Keep the page asked last instead of asking for a page
	// without slug (400 inside the navigation: form without its attributes, or error page) or for a CMS page named
	// like the product (404 shown at the product address). Plain variable: only the last page asked.
	const ROUTE = '/(shop)/[slug]';
	let asked: { slug: string; preview: boolean } | null = null;
	const target = $derived.by(() => {
		if ((appPage.route.id === ROUTE && params.slug) || !asked) asked = { slug: params.slug, preview: appPage.url.searchParams.has('apercu') };
		return asked;
	});
	const data = $derived(await getCmsPage(target));
	const page = $derived(data.page);
</script>

<svelte:head>
	<title>{page.metaTitle || `${page.title} | Clik&Yack`}</title>
	{#if page.metaDescription}<meta name="description" content={page.metaDescription} />{/if}
	{#if data.preview}<meta name="robots" content="noindex" />{/if}
</svelte:head>

<CmsPageView {page} preview={data.preview} catalog={data.catalog} viewer={await getViewer()} />
