<script lang="ts">
	import { page } from '$app/state';
	import Listing from '#lib/catalog/Listing.svelte';
	import { getSectionListing } from '#lib/remote/catalog.remote.js';
	import { listingSearch } from '#lib/types.js';

	let { params } = $props();

	const data = $derived(await getSectionListing({ section: '/produits', path: params.path, search: listingSearch(page.url) }));
</script>

<svelte:head>
	<title>{data.heading} | Clik&amp;Yack</title>
	{#if data.description}<meta name="description" content={data.description} />{/if}
</svelte:head>

<Listing {...data} />
