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

<div class="yack-page-head">
	<h1>Modifier l'adresse</h1>
	<p>Mettre à jour une adresse de votre carnet</p>
</div>

<div class="yack-account-card">
	<AddressForm {address} {countries} submitLabel="Enregistrer" />
</div>
