<script lang="ts">
	import AddressForm from '#lib/account/AddressForm.svelte';
	import { getAddress, getCountries } from '#lib/remote/account.remote.js';

	let { params } = $props();
	const [address, countries] = $derived(await Promise.all([getAddress(params.id), getCountries()]));
</script>

<svelte:head>
	<title>Modifier l'adresse | Clik&amp;Yack</title>
	<meta name="robots" content="noindex" />
</svelte:head>

<div class="mb-4">
	<h1>Modifier l'adresse</h1>
	Mettre à jour une adresse de votre carnet
</div>

<AddressForm {address} {countries} submitLabel="Enregistrer" />
