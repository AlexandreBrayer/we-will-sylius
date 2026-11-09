<script lang="ts">
	// Category page rendered through its CMS template (category > ancestors > default, resolved by
	// Sylius); the product listing follows ?q=&tri=&page=. ?apercu=<template id>: builder preview.
	import { page } from '$app/state';
	import TemplateView from '#lib/blocks/TemplateView.svelte';
	import { getTaxonPage } from '#lib/remote/catalog.remote.js';
	import { getViewer } from '#lib/remote/session.remote.js';
	import { listingSearch } from '#lib/types.js';

	let { params } = $props();

	const preview = $derived(Number(page.url.searchParams.get('apercu')) || null);
	const data = $derived(await getTaxonPage({ section: '/produits', path: params.path, search: listingSearch(page.url), preview }));
	const taxon = $derived(data.context.taxon);
</script>

<svelte:head>
	<title>{taxon.name} | Clik&amp;Yack</title>
	{#if taxon.description}<meta name="description" content={taxon.description} />{/if}
	{#if data.preview}<meta name="robots" content="noindex" />{/if}
</svelte:head>

<TemplateView blocks={data.blocks} taxon={data.context} catalog={data.catalog} viewer={await getViewer()} preview={data.preview} />
