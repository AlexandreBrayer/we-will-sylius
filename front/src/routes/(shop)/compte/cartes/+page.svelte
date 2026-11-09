<script lang="ts">
	// "Mes cartes": cards saved on the provider's payment page for one-click payments. Only the masked
	// card is known (brand, last four digits, expiry); deleting one removes its provider token.
	import { getSavedCards, removeSavedCard } from '#lib/remote/payment.remote.js';

	const cards = $derived(await getSavedCards());
	// Instance of this visit (`.for`): its message does not come back on the next one.
	const remove = removeSavedCard.for('cartes');
	const error = $derived(remove.fields.allIssues()?.[0]?.message);
	const enhanced = remove.enhance(async ({ submit }) => {
		if (!confirm('Supprimer cette carte ?')) return;
		await submit();
	});
</script>

<svelte:head>
	<title>Mes cartes | Clik&amp;Yack</title>
	<meta name="robots" content="noindex" />
</svelte:head>

<div class="yack-page-head">
	<h1>Mes cartes</h1>
	<p>Cartes enregistrées lors d'un paiement, pour payer vos prochaines commandes en un clic.</p>
</div>

{#if error}
	<div class="alert alert-danger" role="alert">{error}</div>
{:else if remove.result}
	<div class="alert alert-success" role="status">{remove.result.message}</div>
{/if}

{#if cards.length === 0}
	<div class="yack-account-card text-center" data-test-no-saved-card>
		<p class="mb-0">Aucune carte enregistrée. Cochez « Enregistrer cette carte » sur la page de paiement sécurisée lors de votre prochain paiement par carte.</p>
	</div>
{:else}
	<form {...enhanced}>
		<section class="yack-account-card" data-test-saved-cards>
			<ul class="list-unstyled mb-0">
				{#each cards as card (card.id)}
					<li class="d-flex flex-wrap gap-3 align-items-center justify-content-between py-2 border-bottom" data-test-saved-card={card.last4}>
						<div>
							<div class="fw-bold">{card.label}</div>
							<div class="small text-body-secondary">
								Expire fin {card.expiry}
								{#if card.expired}<span class="yack-tag yack-tag--muted">Expirée</span>{/if}
							</div>
						</div>
						<button {...remove.fields.id.as('submit', String(card.id))} class="btn btn-sm btn-ghost text-danger" data-test-delete-card>Supprimer</button>
					</li>
				{/each}
			</ul>
			<p class="small text-body-secondary mt-3 mb-0">Le numéro complet de vos cartes n'est jamais conservé par Yack : seul le prestataire de paiement le détient.</p>
		</section>
	</form>
{/if}
