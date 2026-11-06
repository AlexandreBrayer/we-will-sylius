<script lang="ts">
	// 4-step sign-up (port of templates/shop/yack/account/register/step.html.twig). All the fields stay in
	// one form (hidden steps included): with JavaScript the steps change in the browser, each one validated
	// by #lib/account/registration.ts; without it every "Continuer" is a POST (?/etape).
	import { tick } from 'svelte';
	import { enhance } from '$app/forms';
	import Field from '#lib/account/Field.svelte';
	import { STEPS, STEP_COUNT, emptyValues, firstInvalidStep, readValues, type FieldErrors } from '#lib/account/registration.js';
	import Progress from '#lib/layout/Progress.svelte';

	let { data, form } = $props();

	/** Step and errors decided in the browser; null = the server's answer (`form`) applies. */
	let local = $state<{ step: number; errors: FieldErrors } | null>(null);
	let profileChoice = $state<string | null>(null);
	let shippingSameChoice = $state<boolean | null>(null);
	let submitting = $state(false);
	let heading = $state<HTMLElement>();

	const values = $derived(form?.values ?? emptyValues());
	const step = $derived(local?.step ?? form?.step ?? 1);
	const errors: FieldErrors = $derived(local?.errors ?? form?.errors ?? {});
	const profile = $derived(profileChoice ?? values.profile);
	const shippingSame = $derived(shippingSameChoice ?? values.shippingSame === '1');
	const current = $derived(STEPS[step - 1]);

	async function show(next: number, nextErrors: FieldErrors = {}) {
		local = { step: next, errors: nextErrors };
		await tick();
		heading?.scrollIntoView({ block: 'center' });
		const invalid = document.querySelector<HTMLElement>('.yack-register-card fieldset:not([hidden]) .is-invalid');
		(invalid ?? heading)?.focus();
	}
</script>

<svelte:head>
	<title>Créer un compte — {current.label} | Clik&amp;Yack</title>
	<meta name="robots" content="noindex" />
</svelte:head>

{#snippet countrySelect(name: 'billingCountryCode' | 'shippingCountryCode')}
	<div class="field required">
		<label for={name} class="form-label required">Pays</label>
		<select id={name} {name} class="form-select" class:is-invalid={!!errors[name]}>
			<option value="">Sélectionner</option>
			{#each data.countries as country (country.code)}
				<option value={country.code} selected={values[name] === country.code}>{country.name}</option>
			{/each}
		</select>
		{#if errors[name]}<div class="invalid-feedback d-block" data-test-field-error={name}>{errors[name]}</div>{/if}
	</div>
{/snippet}

<div class="container py-5" data-test-yack-register={current.name}>
	<div class="text-center mb-4">
		<h1 class="mb-2">Créer votre compte Clik&amp;Yack</h1>
		<p class="text-body-secondary mb-0">Déjà client ? <a href="/connexion">Connectez-vous</a></p>
	</div>

	<Progress steps={STEPS.map((s) => ({ name: s.name, label: s.label }))} active={step} ariaLabel="Étapes de l'inscription" onselect={(position) => show(position)} />

	<div class="card yack-register-card shadow-sm">
		<div class="card-body p-4 p-lg-5">
			<h2 class="h4 mb-4" tabindex="-1" bind:this={heading}>Étape {step} sur {STEP_COUNT} — {current.label}</h2>

			{#if form?.error}
				<div class="alert alert-danger" role="alert" data-test-register-error>{form.error}</div>
			{/if}

			<form
				method="POST"
				action={step < STEP_COUNT ? '?/etape' : '?/creer'}
				novalidate
				data-test-register-form={current.name}
				use:enhance={({ action, formData, cancel }) => {
					const submitted = readValues(formData);
					if (action.search === '?/precedent') {
						cancel();
						show(Math.max(step - 1, 1));
						return;
					}
					const invalid = firstInvalidStep(submitted, action.search === '?/creer' ? STEP_COUNT : step);
					if (invalid) {
						cancel();
						show(invalid.step, invalid.errors);
						return;
					}
					if (action.search === '?/etape') {
						cancel();
						show(step + 1);
						return;
					}
					submitting = true;
					return async ({ update }) => {
						local = null;
						await update({ reset: false });
						submitting = false;
					};
				}}
			>
				<input type="hidden" name="step" value={step} />

				<fieldset hidden={step !== 1}>
					<legend class="visually-hidden">Vous êtes</legend>
					<div class="row g-3 mb-2" role="radiogroup">
						<div class="col-md-6">
							<input type="radio" class="btn-check" id="profile-pro" name="profile" value="pro" defaultChecked={values.profile === 'pro'} onchange={() => (profileChoice = 'pro')} />
							<label for="profile-pro" class="yack-profile-choice card h-100 border p-4">
								<span class="h5 mb-2">Professionnel (PRO)</span>
								<span class="text-body-secondary">Installateur, chauffagiste, frigoriste… Accès au SAV et aux pièces par n° de série ; ouverture à la commande en ligne sur demande.</span>
							</label>
						</div>
						<div class="col-md-6">
							<input type="radio" class="btn-check" id="profile-ecom" name="profile" value="ecom" defaultChecked={values.profile === 'ecom'} onchange={() => (profileChoice = 'ecom')} />
							<label for="profile-ecom" class="yack-profile-choice card h-100 border p-4">
								<span class="h5 mb-2">Client e-commerce (ECOM)</span>
								<span class="text-body-secondary">Commande en ligne avec prix et disponibilités dès l'activation du compte.</span>
							</label>
						</div>
					</div>
					{#if errors.profile}<div class="invalid-feedback d-block is-invalid" tabindex="-1" data-test-field-error="profile">{errors.profile}</div>{/if}
				</fieldset>

				<fieldset hidden={step !== 2}>
					<legend class="visually-hidden">Société</legend>
					{#if profile !== 'pro'}
						<p class="text-body-secondary">Compte e-commerce : renseignez votre société si vous commandez pour une entreprise (facultatif).</p>
					{/if}
					<div class="row g-3">
						<div class="col-md-12">
							<Field name="companyName" label="Raison sociale" value={values.companyName} error={errors.companyName} required={profile === 'pro'} autocomplete="organization" />
						</div>
						<div class="col-md-6">
							<Field
								name="siret"
								label="SIRET"
								value={values.siret}
								error={errors.siret}
								required={profile === 'pro'}
								help="14 chiffres, sans espaces."
								inputmode="numeric"
								maxlength={17}
								placeholder="123 456 789 00012"
							/>
						</div>
						<div class="col-md-6">
							<Field name="vatNumber" label="N° de TVA intracommunautaire (facultatif)" value={values.vatNumber} error={errors.vatNumber} placeholder="FR00123456789" />
						</div>
					</div>
				</fieldset>

				<fieldset hidden={step !== 3}>
					<legend class="visually-hidden">Contact</legend>
					<div class="row g-3">
						<div class="col-md-6"><Field name="lastName" label="Nom" value={values.lastName} error={errors.lastName} required autocomplete="family-name" /></div>
						<div class="col-md-6"><Field name="firstName" label="Prénom" value={values.firstName} error={errors.firstName} required autocomplete="given-name" /></div>
						<div class="col-md-7">
							<Field
								name="email"
								type="email"
								label="E-mail"
								value={values.email}
								error={errors.email}
								required
								autocomplete="email"
								help="Il servira d'identifiant ; un lien d'activation y sera envoyé."
							/>
						</div>
						<div class="col-md-5">
							<Field name="phone" type="tel" label="Téléphone" value={values.phone} error={errors.phone} required autocomplete="tel" placeholder="04 42 00 00 00" />
						</div>
						<div class="col-md-6">
							<Field name="password" type="password" label="Mot de passe" value={values.password} error={errors.password} required autocomplete="new-password" help="8 caractères minimum." />
						</div>
						<div class="col-md-6">
							<Field name="passwordConfirm" type="password" label="Confirmation du mot de passe" value={values.passwordConfirm} error={errors.passwordConfirm} required autocomplete="new-password" />
						</div>
					</div>
				</fieldset>

				<fieldset hidden={step !== 4}>
					<legend class="visually-hidden">Adresses</legend>
					<h3 class="h6 text-uppercase text-body-secondary mb-3">Adresse de facturation</h3>
					<div class="row g-3 mb-3">
						<div class="col-md-12"><Field name="billingStreet" label="Adresse" value={values.billingStreet} error={errors.billingStreet} required autocomplete="billing street-address" /></div>
						<div class="col-md-3"><Field name="billingPostcode" label="Code postal" value={values.billingPostcode} error={errors.billingPostcode} required autocomplete="billing postal-code" /></div>
						<div class="col-md-5"><Field name="billingCity" label="Ville" value={values.billingCity} error={errors.billingCity} required autocomplete="billing address-level2" /></div>
						<div class="col-md-4">{@render countrySelect('billingCountryCode')}</div>
					</div>
					<div class="form-check">
						<input
							type="checkbox"
							class="form-check-input"
							id="shippingSame"
							name="shippingSame"
							value="1"
							defaultChecked={values.shippingSame === '1'}
							onchange={(event) => (shippingSameChoice = event.currentTarget.checked)}
							data-test-shipping-same
						/>
						<label class="form-check-label" for="shippingSame">Livrer à cette adresse</label>
					</div>
					<div class="mt-3" hidden={shippingSame} data-test-shipping-address>
						<h3 class="h6 text-uppercase text-body-secondary mb-3">Adresse de livraison</h3>
						<div class="row g-3">
							<div class="col-md-12"><Field name="shippingStreet" label="Adresse" value={values.shippingStreet} error={errors.shippingStreet} required autocomplete="shipping street-address" /></div>
							<div class="col-md-3"><Field name="shippingPostcode" label="Code postal" value={values.shippingPostcode} error={errors.shippingPostcode} required autocomplete="shipping postal-code" /></div>
							<div class="col-md-5"><Field name="shippingCity" label="Ville" value={values.shippingCity} error={errors.shippingCity} required autocomplete="shipping address-level2" /></div>
							<div class="col-md-4">{@render countrySelect('shippingCountryCode')}</div>
						</div>
					</div>
				</fieldset>

				<!-- Primary button first in the DOM: Enter submits it, not "Étape précédente". -->
				<div class="d-flex flex-row-reverse justify-content-between align-items-center mt-4 pt-3 border-top">
					<button type="submit" class="btn btn-primary px-4" disabled={submitting} data-test-register-next>
						{step < STEP_COUNT ? 'Continuer' : 'Créer mon compte'}
					</button>
					{#if step > 1}
						<button type="submit" class="btn btn-link px-0" formaction="?/precedent" data-test-register-previous>‹ Étape précédente</button>
					{:else}
						<span></span>
					{/if}
				</div>
			</form>
		</div>
	</div>
</div>
