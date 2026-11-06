<script lang="ts">
	import SavSearch from '#lib/sav/SavSearch.svelte';

	let { data } = $props();
</script>

<svelte:head>
	<title>SAV &amp; pièces détachées | Clik&amp;Yack</title>
</svelte:head>

<SavSearch breadcrumb={data.breadcrumb} />
