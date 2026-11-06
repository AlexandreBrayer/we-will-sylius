<script lang="ts">
	// Checkout step 3 (Sylius checkout/select_payment + Yack escompte badge): choosing a method saves
	// it at once (JS) so the summary shows its escompte, VAT and total before going further.
	import { enhance } from '$app/forms';

	let { data, form } = $props();

	// svelte-ignore state_referenced_locally
	let selected = $state(data.selected);
	let submitting = $state(false);
	let hydrated = $state(false);
	let selectButton = $state<HTMLButtonElement>();
	$effect(() => {
		hydrated = true;
	});
</script>

<svelte:head>
	<title>Paiement | Commande | Clik&amp;Yack</title>
</svelte:head>

<h1 class="h2 mb-4">Paiement</h1>

{#if form?.error}
	<div class="alert alert-danger" role="alert" data-test-checkout-error>{form.error}</div>
{/if}

<form
	method="POST"
	action="?/next"
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
			<div class="card bg-body-tertiary border-0 mb-3" data-test-payment-item={method.code}>
				<label class="card-body d-flex gap-3 align-items-start">
					<input
						class="form-check-input mt-1"
						type="radio"
						name="paymentMethod"
						value={method.code}
						bind:group={selected}
						onchange={() => selectButton?.form?.requestSubmit(selectButton)}
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
		<button class="btn btn-sm btn-outline-secondary" class:d-none={hydrated} type="submit" formaction="?/select" bind:this={selectButton}>
			Actualiser le récapitulatif
		</button>
	</div>

	<div class="d-flex justify-content-between align-items-center">
		<a class="btn btn-link link-reset px-0" href="/commande/livraison">← Modifier la livraison</a>
		<button class="btn btn-primary" type="submit" disabled={submitting || data.methods.length === 0} data-test-next-step>Suivant : récapitulatif</button>
	</div>
</form>
