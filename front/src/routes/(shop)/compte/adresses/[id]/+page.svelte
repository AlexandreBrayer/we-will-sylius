<script lang="ts">
	import AddressForm from '#lib/account/AddressForm.svelte';

	let { data, form } = $props();
</script>

<svelte:head>
	<title>Modifier l'adresse | Clik&amp;Yack</title>
	<meta name="robots" content="noindex" />
</svelte:head>

<div class="mb-4">
	<h1>Modifier l'adresse</h1>
	Mettre à jour une adresse de votre carnet
</div>

<AddressForm address={form?.address ?? data.address} errors={form?.errors} countries={data.countries} submitLabel="Enregistrer" />
