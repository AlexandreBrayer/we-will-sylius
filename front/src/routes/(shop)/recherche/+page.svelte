<script lang="ts">
	import Listing from '#lib/catalog/Listing.svelte';

	let { data } = $props();
</script>

<svelte:head>
	<title>{data.heading} | Clik&amp;Yack</title>
	<meta name="robots" content="noindex" />
</svelte:head>

<Listing {...data} />
