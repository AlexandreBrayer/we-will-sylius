<script lang="ts">
	// Product page rendered through its CMS template (product > category > default, resolved by
	// Sylius): bound blocks read the product (pricing per profile, add-to-cart for ECOM only).
	// ?apercu=<template id>: another / draft template, for an administrator logged into the builder.
	import { page } from '$app/state';
	import TemplateView from '#lib/blocks/TemplateView.svelte';
	import DocumentationPanel from '#lib/documentation/DocumentationPanel.svelte';
	import { getProductPage } from '#lib/remote/catalog.remote.js';
	import { getViewer } from '#lib/remote/session.remote.js';

	let { params } = $props();

	const preview = $derived(Number(page.url.searchParams.get('apercu')) || null);
	const data = $derived(await getProductPage({ slug: params.slug, preview }));
	const product = $derived(data.context.product);
	const viewer = $derived(await getViewer());
</script>

<svelte:head>
	<title>{product.name} | Clik&amp;Yack</title>
	{#if product.shortDescription}<meta name="description" content={product.shortDescription} />{/if}
	{#if data.preview}<meta name="robots" content="noindex" />{/if}
</svelte:head>

<TemplateView blocks={data.blocks} product={data.context} catalog={data.catalog} {viewer} preview={data.preview} />

<!-- Fonction intelligente : assistant documentation, sous le rendu du template. -->
<div class="container mb-5">
	<DocumentationPanel productCode={product.code} {viewer} />
</div>
