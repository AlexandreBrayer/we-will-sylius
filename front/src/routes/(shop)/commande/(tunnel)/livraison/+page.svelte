<script lang="ts">
	// Checkout step 2 (Sylius checkout/select_shipping): carrier or pick-up at a Yack agency.
	import { formatPrice } from '#lib/format.js';
	import { getShippingStep, submitShipping } from '#lib/remote/checkout.remote.js';

	const step = $derived(await getShippingStep());
	const error = $derived(submitShipping.fields.allIssues()?.[0]?.message);
</script>

<svelte:head>
	<title>Livraison | Commande | Clik&amp;Yack</title>
</svelte:head>

<h1 class="h2 mb-4">Livraison</h1>

{#if error}
	<div class="alert alert-danger" role="alert" data-test-checkout-error>{error}</div>
{/if}

<form {...submitShipping.enhance(async ({ submit }) => void (await submit()))}>
	<div class="mb-5">
		{#each step.methods as method (method.code)}
			<div class="card bg-body-tertiary border-0 mb-3" data-test-shipping-item={method.code}>
				<label class="card-body d-flex gap-3 align-items-start">
					<input class="form-check-input mt-1" {...submitShipping.fields.shippingMethod.as('radio', method.code, method.code === step.selected)} required />
					<span class="flex-grow-1">
						<span class="fw-semibold">{method.name}</span>
						{#if method.description}<span class="d-block small text-body-secondary">{method.description}</span>{/if}
					</span>
					<span class="text-nowrap" data-test-shipping-price>{method.price > 0 ? `${formatPrice(method.price)} HT` : 'Gratuit'}</span>
				</label>
			</div>
		{:else}
			<div class="alert alert-warning">Aucun mode de livraison n'est disponible pour cette adresse.</div>
		{/each}
	</div>

	<div class="d-flex justify-content-between align-items-center">
		<a class="btn btn-link link-reset px-0" href="/commande/adresse">← Modifier l'adresse</a>
		<button class="btn btn-primary" type="submit" disabled={submitShipping.pending > 0 || step.methods.length === 0} data-test-next-step>Suivant : paiement</button>
	</div>
</form>
