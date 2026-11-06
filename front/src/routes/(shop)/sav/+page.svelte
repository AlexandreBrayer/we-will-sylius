<script lang="ts">
	import { page } from '$app/state';
	import SavSearch from '#lib/sav/SavSearch.svelte';
	import { getSavHome } from '#lib/remote/sav.remote.js';

	const data = $derived(await getSavHome(page.url.searchParams.get('serial') ?? undefined));
</script>

<svelte:head>
	<title>SAV &amp; pièces détachées | Clik&amp;Yack</title>
</svelte:head>

<SavSearch breadcrumb={data.breadcrumb} />
