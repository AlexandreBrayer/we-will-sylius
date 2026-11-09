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

<h1 class="yack-step-title">Livraison</h1>

{#if error}
	<div class="alert alert-danger" role="alert" data-test-checkout-error>{error}</div>
{/if}

<form {...submitShipping.enhance(async ({ submit }) => void (await submit()))}>
	<div class="yack-choices">
		{#each step.methods as method (method.code)}
			<label class="yack-choice" data-test-shipping-item={method.code}>
				<input class="form-check-input" {...submitShipping.fields.shippingMethod.as('radio', method.code, method.code === step.selected)} required />
				<span class="yack-choice__body">
					<span class="yack-choice__title">{method.name}</span>
					{#if method.description}<span class="yack-choice__text">{method.description}</span>{/if}
				</span>
				<span class="yack-choice__price" data-test-shipping-price>{method.price > 0 ? `${formatPrice(method.price)} HT` : 'Gratuit'}</span>
			</label>
		{:else}
			<div class="alert alert-warning">Aucun mode de livraison n'est disponible pour cette adresse.</div>
		{/each}
	</div>

	<div class="yack-step-nav">
		<a class="btn btn-ghost" href="/commande/adresse">‹ Modifier l'adresse</a>
		<button class="btn btn-primary" type="submit" disabled={submitShipping.pending > 0 || step.methods.length === 0} data-test-next-step>Suivant : paiement</button>
	</div>
</form>
