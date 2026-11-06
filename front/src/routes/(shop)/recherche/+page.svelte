<script lang="ts">
	import { page } from '$app/state';
	import Listing from '#lib/catalog/Listing.svelte';
	import { getSearchListing } from '#lib/remote/catalog.remote.js';
	import { listingSearch } from '#lib/types.js';

	const data = $derived(await getSearchListing(listingSearch(page.url)));
</script>

<svelte:head>
	<title>{data.heading} | Clik&amp;Yack</title>
	<meta name="robots" content="noindex" />
</svelte:head>

<Listing {...data} />
