<script lang="ts">
	import { enhance } from '$app/forms';
	import AddressBlock from '#lib/account/AddressBlock.svelte';

	let { data, form } = $props();
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

{#if form?.error}
	<div class="alert alert-danger" role="alert">{form.error}</div>
{:else if form?.message}
	<div class="alert alert-success" role="status">{form.message}</div>
{:else if data.saved}
	<div class="alert alert-success" role="status">Adresse enregistrée.</div>
{/if}

{#snippet actions(id: number, isDefault: boolean)}
	<div class="d-flex flex-wrap gap-2 mt-3">
		<a class="btn btn-sm btn-icon btn-outline-gray" href="/compte/adresses/{id}" data-test-edit-button>Modifier</a>
		{#if !isDefault}
			<form method="POST" action="?/parDefaut" use:enhance>
				<input type="hidden" name="id" value={id} />
				<button class="btn btn-sm btn-icon btn-outline-gray" type="submit" data-test-set-as-default>Définir par défaut</button>
			</form>
			<form
				method="POST"
				action="?/supprimer"
				use:enhance={({ cancel }) => {
					if (!confirm('Supprimer cette adresse ?')) cancel();
				}}
			>
				<input type="hidden" name="id" value={id} />
				<button class="btn btn-sm btn-icon btn-outline-gray text-danger" type="submit" data-test-delete-button>Supprimer</button>
			</form>
		{/if}
	</div>
{/snippet}

{#if !data.defaultAddress && data.addresses.length === 0}
	<p class="text-body-secondary">Votre carnet d'adresses est vide.</p>
{/if}

{#if data.defaultAddress}
	<div class="card border-0 bg-body-tertiary mb-3">
		<div class="card-body" data-test-default-address>
			<span class="badge text-bg-dark mb-2">Adresse par défaut</span>
			<div class="small text-body-secondary mb-2">Utilisée comme adresse de facturation de vos commandes.</div>
			<AddressBlock address={data.defaultAddress} />
			{@render actions(data.defaultAddress.id, true)}
		</div>
	</div>
{/if}

<div data-test-addresses>
	{#each data.addresses as address (address.id)}
		<div class="card border-0 bg-body-tertiary mb-3">
			<div class="card-body">
				<AddressBlock {address} />
				{@render actions(address.id, false)}
			</div>
		</div>
	{/each}
</div>
