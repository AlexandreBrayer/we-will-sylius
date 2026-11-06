<script lang="ts">
	// Checkout step 1 (Sylius checkout/address): delivery address from the address book or typed in,
	// billing address identical (default) or different.
	import { enhance } from '$app/forms';
	import AddressChooser from './AddressChooser.svelte';

	let { data, form } = $props();

	// svelte-ignore state_referenced_locally
	let sameBilling = $state(form?.sameBilling ?? data.sameBilling);
	let submitting = $state(false);
	// Without JS the billing form stays visible (used only when the box is unchecked).
	let hydrated = $state(false);
	$effect(() => {
		hydrated = true;
	});
</script>

<svelte:head>
	<title>Adresse | Commande | Clik&amp;Yack</title>
</svelte:head>

<h1 class="h2 mb-4">Adresse</h1>

{#if form?.error}
	<div class="alert alert-danger" role="alert" data-test-checkout-error>{form.error}</div>
{/if}

<form
	method="POST"
	use:enhance={() => {
		submitting = true;
		return async ({ update }) => {
			await update({ reset: false });
			submitting = false;
		};
	}}
>
	<h2 class="h5 mb-3">Adresse de livraison</h2>
	<AddressChooser
		prefix="shipping"
		book={data.book}
		countries={data.countries}
		choice={form?.shipping?.choice ?? data.shipping.choice}
		values={form?.shipping ?? data.shipping.values}
	/>

	<div class="form-check mb-4">
		<input class="form-check-input" type="checkbox" id="sameBilling" name="sameBilling" value="1" bind:checked={sameBilling} data-test-same-billing />
		<label class="form-check-label" for="sameBilling">Adresse de facturation identique à l'adresse de livraison</label>
	</div>

	{#if !sameBilling || !hydrated}
		<h2 class="h5 mb-3">Adresse de facturation{#if !hydrated}<small class="text-body-secondary fw-normal"> (si différente)</small>{/if}</h2>
		<AddressChooser
			prefix="billing"
			book={data.book}
			countries={data.countries}
			choice={form?.billing?.choice ?? data.billing.choice}
			values={form?.billing ?? data.billing.values}
		/>
	{/if}

	<div class="d-flex justify-content-between align-items-center mt-5">
		<a class="btn btn-link link-reset px-0" href="/panier">← Retour au panier</a>
		<button class="btn btn-primary" type="submit" disabled={submitting} data-test-next-step>Suivant : livraison</button>
	</div>
</form>
