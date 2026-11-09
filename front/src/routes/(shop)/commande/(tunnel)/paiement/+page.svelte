<script lang="ts">
	// Checkout step 3 (Sylius checkout/select_payment + Yack escompte badge). With JS, choosing a
	// method saves it at once (`selectPaymentMethod` command, the summary of the layout comes back
	// with the escompte, VAT and total); without JS, "Actualiser le total" does the same.
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

<h1 class="yack-step-title">Paiement</h1>

{#if error}
	<div class="alert alert-danger" role="alert" data-test-checkout-error>{error}</div>
{/if}

<form {...submitPayment.enhance(async ({ submit }) => void (await submit()))}>
	<div class="yack-choices">
		{#each step.methods as method (method.code)}
			<label class="yack-choice" data-test-payment-item={method.code}>
				<input
					class="form-check-input"
					{...submitPayment.fields.paymentMethod.as('radio', method.code, method.code === step.selected)}
					onchange={() => select(method.code)}
					required
				/>
				<span class="yack-choice__body">
					<span class="yack-choice__title">
						{method.name}
						{#if method.escompteLabel}
							<span class="yack-tag yack-tag--secondary" data-test-payment-escompte>{method.escompteLabel}</span>
						{:else}
							<span class="yack-tag yack-tag--muted" data-test-payment-escompte>Prix normal</span>
						{/if}
					</span>
					{#if method.description}<span class="yack-choice__text">{method.description}</span>{/if}
				</span>
			</label>
		{:else}
			<div class="alert alert-warning">Aucun mode de paiement n'est disponible.</div>
		{/each}
	</div>
	{#if !hydrated}
		<button class="btn btn-sm btn-ghost" {...submitPayment.fields.action.as('submit', 'select')} data-test-payment-refresh>Actualiser le total</button>
	{/if}

	<div class="yack-step-nav">
		<a class="btn btn-ghost" href="/commande/livraison">‹ Modifier la livraison</a>
		<button
			class="btn btn-primary"
			{...submitPayment.fields.action.as('submit', 'next')}
			disabled={submitPayment.pending > 0 || selectPaymentMethod.pending > 0 || step.methods.length === 0}
			data-test-next-step
		>
			Suivant : validation
		</button>
	</div>
</form>
