<script lang="ts">
	// One address of the checkout (fields `<prefix>.*` of `submitAddresses`): a saved address of the
	// address book, or a new one typed in. Without JS the form of the new address stays visible; the
	// radio decides which one is sent.
	import type { SavedAddress } from '#lib/order/types.js';
	import { submitAddresses } from '#lib/remote/checkout.remote.js';
	import type { AddressInput } from '#lib/server/checkout.js';

	let {
		prefix,
		book,
		countries,
		initial
	}: {
		prefix: 'shipping' | 'billing';
		book: SavedAddress[];
		countries: { code: string; name: string }[];
		/** Pre-selection (`choice`: saved address id or "new") and pre-fill of the new address. */
		initial: AddressInput;
	} = $props();

	const fields = $derived(submitAddresses.fields[prefix]);
	const selected = $derived(fields.choice.value() ?? initial.choice);

	let hydrated = $state(false);
	$effect(() => {
		hydrated = true;
	});

	const countryName = (code: string) => countries.find((c) => c.code === code)?.name ?? code;
</script>

<div class="yack-choices yack-choices--2" data-test-address-book={prefix}>
	{#each book as address (address.id)}
		<label class="yack-choice">
			<input class="form-check-input" {...fields.choice.as('radio', String(address.id), initial.choice === String(address.id))} data-test-address-choice={address.id} />
			<span class="yack-choice__body">
				<span class="yack-choice__title">{address.firstName} {address.lastName}{#if address.company}, {address.company}{/if}</span>
				<span class="yack-choice__text">
					{address.street}, {address.postcode} {address.city}, {countryName(address.countryCode)}
					{#if address.phoneNumber}<br />{address.phoneNumber}{/if}
				</span>
			</span>
		</label>
	{/each}
	<label class="yack-choice">
		<input class="form-check-input" {...fields.choice.as('radio', 'new', initial.choice === 'new')} data-test-address-choice="new" />
		<span class="yack-choice__body">
			<span class="yack-choice__title">{book.length > 0 ? 'Nouvelle adresse' : 'Saisir une adresse'}</span>
			<span class="yack-choice__text">Livraison sur un chantier ou à une autre adresse</span>
		</span>
	</label>
</div>

{#if !hydrated || selected === 'new'}
	<div class="row g-3 mb-4" data-test-address-form={prefix}>
		<div class="col-sm-6">
			<label class="form-label required" for="{prefix}-firstName">Prénom</label>
			<input class="form-control" id="{prefix}-firstName" {...fields.firstName.as('text', initial.firstName)} autocomplete="{prefix} given-name" required={hydrated} />
		</div>
		<div class="col-sm-6">
			<label class="form-label required" for="{prefix}-lastName">Nom</label>
			<input class="form-control" id="{prefix}-lastName" {...fields.lastName.as('text', initial.lastName)} autocomplete="{prefix} family-name" required={hydrated} />
		</div>
		<div class="col-12">
			<label class="form-label" for="{prefix}-company">Société</label>
			<input class="form-control" id="{prefix}-company" {...fields.company.as('text', initial.company)} autocomplete="{prefix} organization" />
		</div>
		<div class="col-12">
			<label class="form-label required" for="{prefix}-street">Adresse</label>
			<input class="form-control" id="{prefix}-street" {...fields.street.as('text', initial.street)} autocomplete="{prefix} street-address" required={hydrated} />
		</div>
		<div class="col-sm-4">
			<label class="form-label required" for="{prefix}-postcode">Code postal</label>
			<input class="form-control" id="{prefix}-postcode" {...fields.postcode.as('text', initial.postcode)} autocomplete="{prefix} postal-code" required={hydrated} />
		</div>
		<div class="col-sm-8">
			<label class="form-label required" for="{prefix}-city">Ville</label>
			<input class="form-control" id="{prefix}-city" {...fields.city.as('text', initial.city)} autocomplete="{prefix} address-level2" required={hydrated} />
		</div>
		<div class="col-sm-6">
			<label class="form-label required" for="{prefix}-countryCode">Pays</label>
			<select class="form-select" id="{prefix}-countryCode" {...fields.countryCode.as('select', initial.countryCode)} required={hydrated}>
				{#each countries as country (country.code)}
					<option value={country.code}>{country.name}</option>
				{/each}
			</select>
		</div>
		<div class="col-sm-6">
			<label class="form-label" for="{prefix}-phoneNumber">Téléphone</label>
			<input class="form-control" id="{prefix}-phoneNumber" {...fields.phoneNumber.as('tel', initial.phoneNumber)} autocomplete="{prefix} tel" />
		</div>
	</div>
{/if}
