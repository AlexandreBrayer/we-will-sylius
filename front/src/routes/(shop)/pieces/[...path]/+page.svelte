<script lang="ts">
	import Listing from '#lib/catalog/Listing.svelte';

	let { data } = $props();
</script>

<svelte:head>
	<title>{data.heading} | Clik&amp;Yack</title>
	{#if data.description}<meta name="description" content={data.description} />{/if}
</svelte:head>

<Listing {...data} />
