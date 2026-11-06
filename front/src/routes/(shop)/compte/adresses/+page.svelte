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

<div class="mb-4 d-flex flex-wrap align-items-start justify-content-between gap-3">
	<div>
		<h1>Carnet d'adresses</h1>
		Gérer vos adresses enregistrées
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
	<div class="d-flex flex-wrap gap-2 mt-3">
		<a class="btn btn-sm btn-icon btn-outline-gray" href="/compte/adresses/{id}" data-test-edit-button>Modifier</a>
		{#if !isDefault}
			<button {...manage.fields.intent.as('submit', `defaut:${id}`)} class="btn btn-sm btn-icon btn-outline-gray" data-test-set-as-default>
				Définir par défaut
			</button>
			<button {...manage.fields.intent.as('submit', `supprimer:${id}`)} class="btn btn-sm btn-icon btn-outline-gray text-danger" data-test-delete-button>
				Supprimer
			</button>
		{/if}
	</div>
{/snippet}

{#if !book.defaultAddress && book.addresses.length === 0}
	<p class="text-body-secondary">Votre carnet d'adresses est vide.</p>
{/if}

<!-- One form for the whole list: each button posts its own `intent`. -->
<form {...enhanced}>
	{#if book.defaultAddress}
		<div class="card border-0 bg-body-tertiary mb-3">
			<div class="card-body" data-test-default-address>
				<span class="badge text-bg-dark mb-2">Adresse par défaut</span>
				<div class="small text-body-secondary mb-2">Utilisée comme adresse de facturation de vos commandes.</div>
				<AddressBlock address={book.defaultAddress} />
				{@render actions(book.defaultAddress.id, true)}
			</div>
		</div>
	{/if}

	<div data-test-addresses>
		{#each book.addresses as address (address.id)}
			<div class="card border-0 bg-body-tertiary mb-3">
				<div class="card-body">
					<AddressBlock {address} />
					{@render actions(address.id, false)}
				</div>
			</div>
		{/each}
	</div>
</form>
