<script lang="ts">
	import AddressForm from '#lib/account/AddressForm.svelte';
	import { getCountries } from '#lib/remote/account.remote.js';
</script>

<svelte:head>
	<title>Ajouter une adresse | Clik&amp;Yack</title>
	<meta name="robots" content="noindex" />
</svelte:head>

<div class="yack-page-head">
	<h1>Ajouter une adresse</h1>
	<p>Ajouter une nouvelle adresse à votre carnet</p>
</div>

<div class="yack-account-card">
	<AddressForm address={null} countries={await getCountries()} submitLabel="Ajouter" />
</div>
