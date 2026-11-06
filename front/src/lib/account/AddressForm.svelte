<script lang="ts">
	// Address book form (create / edit): fields of a Sylius address, errors from the API 422.
	// One `saveAddress` instance per address (`.for`): what was typed in another one never shows here.
	import type { Country } from './types.js';
	import type { SavedAddress } from '#lib/order/types.js';
	import { saveAddress } from '#lib/remote/account.remote.js';
	import Field from './Field.svelte';

	let {
		address,
		countries,
		submitLabel
	}: {
		/** Address being edited, null for a new one. */
		address: SavedAddress | null;
		countries: Country[];
		submitLabel: string;
	} = $props();

	const form = $derived(saveAddress.for(address ? String(address.id) : 'nouvelle'));
	const fields = $derived(form.fields);
	const countryError = $derived(fields.countryCode.issues()?.[0]?.message);
</script>

<form {...form} novalidate data-test-address-form>
	<div class="row g-3 mb-4">
		<div class="col-md-6"><Field field={fields.firstName} id="firstName" label="Prénom" value={address?.firstName} required autocomplete="given-name" /></div>
		<div class="col-md-6"><Field field={fields.lastName} id="lastName" label="Nom" value={address?.lastName} required autocomplete="family-name" /></div>
		<div class="col-md-6"><Field field={fields.company} id="company" label="Société" value={address?.company} autocomplete="organization" /></div>
		<div class="col-md-6"><Field field={fields.phoneNumber} id="phoneNumber" type="tel" label="Téléphone" value={address?.phoneNumber} autocomplete="tel" /></div>
		<div class="col-md-12"><Field field={fields.street} id="street" label="Adresse" value={address?.street} required autocomplete="street-address" /></div>
		<div class="col-md-3"><Field field={fields.postcode} id="postcode" label="Code postal" value={address?.postcode} required autocomplete="postal-code" /></div>
		<div class="col-md-5"><Field field={fields.city} id="city" label="Ville" value={address?.city} required autocomplete="address-level2" /></div>
		<div class="col-md-4">
			<div class="field required">
				<label for="countryCode" class="form-label required">Pays</label>
				<select {...fields.countryCode.as('select', address?.countryCode || 'FR')} id="countryCode" class="form-select" class:is-invalid={!!countryError}>
					<option value="">Sélectionner</option>
					{#each countries as option (option.code)}
						<option value={option.code}>{option.name}</option>
					{/each}
				</select>
				{#if countryError}<div class="invalid-feedback d-block">{countryError}</div>{/if}
			</div>
		</div>
	</div>
	<div class="d-flex gap-2">
		<button type="submit" class="btn btn-primary" disabled={form.pending > 0} data-test-address-submit>{submitLabel}</button>
		<a href="/compte/adresses" class="btn btn-outline-gray">Annuler</a>
	</div>
</form>
