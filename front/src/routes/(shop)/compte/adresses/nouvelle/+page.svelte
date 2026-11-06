<script lang="ts">
	import AddressForm from '#lib/account/AddressForm.svelte';
	import { getCountries } from '#lib/remote/account.remote.js';
</script>

<svelte:head>
	<title>Ajouter une adresse | Clik&amp;Yack</title>
	<meta name="robots" content="noindex" />
</svelte:head>

<div class="mb-4">
	<h1>Ajouter une adresse</h1>
	Ajouter une nouvelle adresse à votre carnet
</div>

<AddressForm address={null} countries={await getCountries()} submitLabel="Ajouter" />
