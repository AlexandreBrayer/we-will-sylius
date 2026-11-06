<script lang="ts">
	// Checkout step 3 (Sylius checkout/select_payment + Yack escompte badge). With JS, choosing a
	// method saves it at once (`selectPaymentMethod` command, the summary of the layout comes back
	// with the escompte, VAT and total); without JS, "Actualiser le récapitulatif" does the same.
	import { getPaymentStep, selectPaymentMethod, submitPayment } from '#lib/remote/checkout.remote.js';

	const step = $derived(await getPaymentStep());
	let selectError = $state<string | null>(null);
	const error = $derived(selectError ?? submitPayment.fields.allIssues()?.[0]?.message);

	let hydrated = $state(false);
	$effect(() => {
		hydrated = true;
	});

	async function select(method: string) {
		selectError = (await selectPaymentMethod(method)).error;
	}
</script>

<svelte:head>
	<title>Paiement | Commande | Clik&amp;Yack</title>
</svelte:head>

<h1 class="h2 mb-4">Paiement</h1>

{#if error}
	<div class="alert alert-danger" role="alert" data-test-checkout-error>{error}</div>
{/if}

<form {...submitPayment.enhance(async ({ submit }) => void (await submit()))}>
	<div class="mb-5">
		{#each step.methods as method (method.code)}
			<div class="card bg-body-tertiary border-0 mb-3" data-test-payment-item={method.code}>
				<label class="card-body d-flex gap-3 align-items-start">
					<input
						class="form-check-input mt-1"
						{...submitPayment.fields.paymentMethod.as('radio', method.code, method.code === step.selected)}
						onchange={() => select(method.code)}
						required
					/>
					<span class="flex-grow-1">
						<span class="fw-semibold">{method.name}</span>
						{#if method.escompteLabel}
							<span class="badge text-bg-success ms-4" data-test-payment-escompte>{method.escompteLabel}</span>
						{:else}
							<span class="badge text-bg-light border ms-4" data-test-payment-escompte>Prix normal</span>
						{/if}
						{#if method.description}<span class="d-block small text-body-secondary mt-1">{method.description}</span>{/if}
					</span>
				</label>
			</div>
		{:else}
			<div class="alert alert-warning">Aucun mode de paiement n'est disponible.</div>
		{/each}
		{#if !hydrated}
			<button class="btn btn-sm btn-outline-secondary" {...submitPayment.fields.action.as('submit', 'select')} data-test-payment-refresh>
				Actualiser le récapitulatif
			</button>
		{/if}
	</div>

	<div class="d-flex justify-content-between align-items-center">
		<a class="btn btn-link link-reset px-0" href="/commande/livraison">← Modifier la livraison</a>
		<button
			class="btn btn-primary"
			{...submitPayment.fields.action.as('submit', 'next')}
			disabled={submitPayment.pending > 0 || selectPaymentMethod.pending > 0 || step.methods.length === 0}
			data-test-next-step
		>
			Suivant : récapitulatif
		</button>
	</div>
</form>
