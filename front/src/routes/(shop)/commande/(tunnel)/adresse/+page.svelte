<script lang="ts">
	// Checkout step 1 (Sylius checkout/address): delivery address from the address book or typed in,
	// billing address identical (default) or different.
	import { getAddressStep, submitAddresses } from '#lib/remote/checkout.remote.js';
	import AddressChooser from './AddressChooser.svelte';

	const step = $derived(await getAddressStep());
	const sameBilling = $derived(submitAddresses.fields.sameBilling.value() ?? step.sameBilling);
	const error = $derived(submitAddresses.fields.allIssues()?.[0]?.message);

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

{#if error}
	<div class="alert alert-danger" role="alert" data-test-checkout-error>{error}</div>
{/if}

<!-- Values kept after an error (the default enhance empties the form). -->
<form {...submitAddresses.enhance(async ({ submit }) => void (await submit()))}>
	<h2 class="h5 mb-3">Adresse de livraison</h2>
	<AddressChooser prefix="shipping" book={step.book} countries={step.countries} initial={step.shipping} />

	<div class="form-check mb-4">
		<input class="form-check-input" id="sameBilling" {...submitAddresses.fields.sameBilling.as('checkbox', step.sameBilling)} data-test-same-billing />
		<label class="form-check-label" for="sameBilling">Adresse de facturation identique à l'adresse de livraison</label>
	</div>

	{#if !sameBilling || !hydrated}
		<h2 class="h5 mb-3">Adresse de facturation{#if !hydrated}<small class="text-body-secondary fw-normal"> (si différente)</small>{/if}</h2>
		<AddressChooser prefix="billing" book={step.book} countries={step.countries} initial={step.billing} />
	{/if}

	<div class="d-flex justify-content-between align-items-center mt-5">
		<a class="btn btn-link link-reset px-0" href="/panier">← Retour au panier</a>
		<button class="btn btn-primary" type="submit" disabled={submitAddresses.pending > 0} data-test-next-step>Suivant : livraison</button>
	</div>
</form>
