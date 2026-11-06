<script lang="ts">
	// Address book form (create / edit): fields of a Sylius address, errors from the API 422.
	import { enhance } from '$app/forms';
	import type { Country } from './types.js';
	import type { OrderAddress } from '#lib/order/types.js';
	import Field from './Field.svelte';

	let {
		address,
		errors = {},
		countries,
		submitLabel
	}: {
		address: OrderAddress | null;
		errors?: Record<string, string>;
		countries: Country[];
		submitLabel: string;
	} = $props();

	let submitting = $state(false);
	const country = $derived(address?.countryCode || 'FR');
</script>

<form
	method="POST"
	novalidate
	data-test-address-form
	use:enhance={() => {
		submitting = true;
		return async ({ update }) => {
			await update({ reset: false });
			submitting = false;
		};
	}}
>
	<div class="row g-3 mb-4">
		<div class="col-md-6"><Field name="firstName" label="Prénom" value={address?.firstName} error={errors.firstName} required autocomplete="given-name" /></div>
		<div class="col-md-6"><Field name="lastName" label="Nom" value={address?.lastName} error={errors.lastName} required autocomplete="family-name" /></div>
		<div class="col-md-6"><Field name="company" label="Société" value={address?.company} error={errors.company} autocomplete="organization" /></div>
		<div class="col-md-6"><Field name="phoneNumber" type="tel" label="Téléphone" value={address?.phoneNumber} error={errors.phoneNumber} autocomplete="tel" /></div>
		<div class="col-md-12"><Field name="street" label="Adresse" value={address?.street} error={errors.street} required autocomplete="street-address" /></div>
		<div class="col-md-3"><Field name="postcode" label="Code postal" value={address?.postcode} error={errors.postcode} required autocomplete="postal-code" /></div>
		<div class="col-md-5"><Field name="city" label="Ville" value={address?.city} error={errors.city} required autocomplete="address-level2" /></div>
		<div class="col-md-4">
			<div class="field required">
				<label for="countryCode" class="form-label required">Pays</label>
				<select id="countryCode" name="countryCode" class="form-select" class:is-invalid={!!errors.countryCode}>
					<option value="">Sélectionner</option>
					{#each countries as option (option.code)}
						<option value={option.code} selected={country === option.code}>{option.name}</option>
					{/each}
				</select>
				{#if errors.countryCode}<div class="invalid-feedback d-block">{errors.countryCode}</div>{/if}
			</div>
		</div>
	</div>
	<div class="d-flex gap-2">
		<button type="submit" class="btn btn-primary" disabled={submitting} data-test-address-submit>{submitLabel}</button>
		<a href="/compte/adresses" class="btn btn-outline-gray">Annuler</a>
	</div>
</form>
