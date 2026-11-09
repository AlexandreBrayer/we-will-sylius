<script lang="ts">
	import { page } from '$app/state';
	import AddressBlock from '#lib/account/AddressBlock.svelte';
	import { getAddressBook, manageAddress } from '#lib/remote/account.remote.js';

	const book = $derived(await getAddressBook());
	// Instance of this visit (`.for`): its message does not come back on the next one.
	const manage = manageAddress.for('carnet');
	const error = $derived(manage.fields.allIssues()?.[0]?.message);

	// "Supprimer" asks for a confirmation first (without JS: deleted directly, as before).
	const enhanced = manage.enhance(async ({ fields, submit }) => {
		if (fields.intent.value()?.startsWith('supprimer:') && !confirm('Supprimer cette adresse ?')) return;
		await submit();
	});
</script>

<svelte:head>
	<title>Carnet d'adresses | Clik&amp;Yack</title>
	<meta name="robots" content="noindex" />
</svelte:head>

<div class="yack-page-head d-flex flex-wrap align-items-end justify-content-between gap-3">
	<div>
		<h1>Carnet d'adresses</h1>
		<p>Gérer vos adresses enregistrées</p>
	</div>
	<a href="/compte/adresses/nouvelle" class="btn btn-primary" data-test-add-address>Ajouter une adresse</a>
</div>

{#if error}
	<div class="alert alert-danger" role="alert">{error}</div>
{:else if manage.result}
	<div class="alert alert-success" role="status">{manage.result.message}</div>
{:else if page.url.searchParams.get('enregistree') === '1'}
	<div class="alert alert-success" role="status">Adresse enregistrée.</div>
{/if}

{#snippet actions(id: number, isDefault: boolean)}
	<div class="yack-address-card__actions">
		<a class="btn btn-sm btn-ghost" href="/compte/adresses/{id}" data-test-edit-button>Modifier</a>
		{#if !isDefault}
			<button {...manage.fields.intent.as('submit', `defaut:${id}`)} class="btn btn-sm btn-ghost" data-test-set-as-default>Définir par défaut</button>
			<button {...manage.fields.intent.as('submit', `supprimer:${id}`)} class="btn btn-sm btn-ghost text-danger" data-test-delete-button>Supprimer</button>
		{/if}
	</div>
{/snippet}

{#if !book.defaultAddress && book.addresses.length === 0}
	<div class="yack-account-card text-center">
		<p class="mb-3">Votre carnet d'adresses est vide.</p>
		<a href="/compte/adresses/nouvelle" class="btn btn-primary">Ajouter une adresse</a>
	</div>
{/if}

<!-- One form for the whole list: each button posts its own `intent`. -->
<form {...enhanced}>
	<div class="yack-address-grid">
		{#if book.defaultAddress}
			<div class="yack-address-card yack-address-card--default" data-test-default-address>
				<div class="d-flex flex-wrap gap-2 align-items-center">
					<span class="yack-tag yack-tag--primary">Adresse par défaut</span>
					<span class="small text-body-secondary">Facturation de vos commandes</span>
				</div>
				<AddressBlock address={book.defaultAddress} />
				{@render actions(book.defaultAddress.id, true)}
			</div>
		{/if}
		<div class="yack-address-grid__list" data-test-addresses>
			{#each book.addresses as address (address.id)}
				<div class="yack-address-card">
					<AddressBlock {address} />
					{@render actions(address.id, false)}
				</div>
			{/each}
		</div>
	</div>
</form>
