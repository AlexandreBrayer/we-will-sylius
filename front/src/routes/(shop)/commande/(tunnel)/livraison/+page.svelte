<script lang="ts">
	// Checkout step 2 (Sylius checkout/select_shipping): carrier or pick-up at a Yack agency.
	import { enhance } from '$app/forms';
	import { formatPrice } from '#lib/format.js';

	let { data, form } = $props();

	// svelte-ignore state_referenced_locally
	let selected = $state(data.selected);
	let submitting = $state(false);
</script>

<svelte:head>
	<title>Livraison | Commande | Clik&amp;Yack</title>
</svelte:head>

<h1 class="h2 mb-4">Livraison</h1>

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
	<div class="mb-5">
		{#each data.methods as method (method.code)}
			<div class="card bg-body-tertiary border-0 mb-3" data-test-shipping-item={method.code}>
				<label class="card-body d-flex gap-3 align-items-start">
					<input class="form-check-input mt-1" type="radio" name="shippingMethod" value={method.code} bind:group={selected} required />
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
		<button class="btn btn-primary" type="submit" disabled={submitting || data.methods.length === 0} data-test-next-step>Suivant : paiement</button>
	</div>
</form>
