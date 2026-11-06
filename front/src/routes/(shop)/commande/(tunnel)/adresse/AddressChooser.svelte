<script lang="ts">
	// One address of the checkout: a saved address of the address book, or a new one typed in.
	// Without JS the form of the new address stays visible; the radio decides which one is sent.
	import type { OrderAddress, SavedAddress } from '#lib/order/types.js';

	let {
		prefix,
		book,
		countries,
		choice,
		values
	}: {
		prefix: 'shipping' | 'billing';
		book: SavedAddress[];
		countries: { code: string; name: string }[];
		/** Saved address id, or "new". */
		choice: string;
		/** Pre-fill of the new address. */
		values: Partial<Record<keyof OrderAddress, string | null>> | null;
	} = $props();

	// svelte-ignore state_referenced_locally
	let selected = $state(choice);
	let hydrated = $state(false);
	$effect(() => {
		hydrated = true;
	});

	const countryName = (code: string) => countries.find((c) => c.code === code)?.name ?? code;
	const field = (name: keyof OrderAddress) => values?.[name] ?? '';
</script>

<div class="d-grid gap-2 mb-3" data-test-address-book={prefix}>
	{#each book as address (address.id)}
		<label class="card bg-body-tertiary border-0" class:border-primary={selected === String(address.id)}>
			<span class="card-body d-flex gap-3">
				<input class="form-check-input mt-1" type="radio" name="{prefix}-choice" value={String(address.id)} bind:group={selected} data-test-address-choice={address.id} />
				<span>
					<span class="fw-semibold">{address.firstName} {address.lastName}</span>{#if address.company}, {address.company}{/if}<br />
					{address.street}, {address.postcode} {address.city}, {countryName(address.countryCode)}
					{#if address.phoneNumber}<br /><span class="small text-body-secondary">{address.phoneNumber}</span>{/if}
				</span>
			</span>
		</label>
	{/each}
	<label class="card bg-body-tertiary border-0">
		<span class="card-body d-flex gap-3">
			<input class="form-check-input mt-1" type="radio" name="{prefix}-choice" value="new" bind:group={selected} data-test-address-choice="new" />
			<span class="fw-semibold">{book.length > 0 ? 'Nouvelle adresse' : 'Saisir une adresse'}</span>
		</span>
	</label>
</div>

{#if !hydrated || selected === 'new'}
	<div class="row g-3 mb-3" data-test-address-form={prefix}>
		<div class="col-sm-6">
			<label class="form-label required" for="{prefix}-firstName">Prénom</label>
			<input class="form-control" id="{prefix}-firstName" name="{prefix}-firstName" value={field('firstName')} autocomplete="{prefix} given-name" required={hydrated} />
		</div>
		<div class="col-sm-6">
			<label class="form-label required" for="{prefix}-lastName">Nom</label>
			<input class="form-control" id="{prefix}-lastName" name="{prefix}-lastName" value={field('lastName')} autocomplete="{prefix} family-name" required={hydrated} />
		</div>
		<div class="col-12">
			<label class="form-label" for="{prefix}-company">Société</label>
			<input class="form-control" id="{prefix}-company" name="{prefix}-company" value={field('company')} autocomplete="{prefix} organization" />
		</div>
		<div class="col-12">
			<label class="form-label required" for="{prefix}-street">Adresse</label>
			<input class="form-control" id="{prefix}-street" name="{prefix}-street" value={field('street')} autocomplete="{prefix} street-address" required={hydrated} />
		</div>
		<div class="col-sm-4">
			<label class="form-label required" for="{prefix}-postcode">Code postal</label>
			<input class="form-control" id="{prefix}-postcode" name="{prefix}-postcode" value={field('postcode')} autocomplete="{prefix} postal-code" required={hydrated} />
		</div>
		<div class="col-sm-8">
			<label class="form-label required" for="{prefix}-city">Ville</label>
			<input class="form-control" id="{prefix}-city" name="{prefix}-city" value={field('city')} autocomplete="{prefix} address-level2" required={hydrated} />
		</div>
		<div class="col-sm-6">
			<label class="form-label required" for="{prefix}-countryCode">Pays</label>
			<select class="form-select" id="{prefix}-countryCode" name="{prefix}-countryCode" required={hydrated}>
				{#each countries as country (country.code)}
					<option value={country.code} selected={country.code === (field('countryCode') || 'FR')}>{country.name}</option>
				{/each}
			</select>
		</div>
		<div class="col-sm-6">
			<label class="form-label" for="{prefix}-phoneNumber">Téléphone</label>
			<input class="form-control" id="{prefix}-phoneNumber" name="{prefix}-phoneNumber" type="tel" value={field('phoneNumber')} autocomplete="{prefix} tel" />
		</div>
	</div>
{/if}
