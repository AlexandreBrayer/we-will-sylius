<script lang="ts">
	// 4-step sign-up (port of templates/shop/yack/account/register/step.html.twig). All the fields stay in
	// one `register` form (hidden steps included): with JavaScript the steps change in the browser, each one
	// validated by #lib/account/registration.ts; without it every button posts its `intent` (etape,
	// precedent, creer) and the server answers the step to show.
	import { tick } from 'svelte';
	import { page } from '$app/state';
	import Field from '#lib/account/Field.svelte';
	import { LOGIN_PATH, PROFILE_PARAM, RETURN_PARAM, presetProfile, safeReturnPath } from '#lib/account/returnPath.js';
	import { STEPS, STEP_COUNT, emptyValues, firstInvalidStep, issueStep, toValues, type Field as FieldName, type FieldErrors } from '#lib/account/registration.js';
	import Progress from '#lib/layout/Progress.svelte';
	import { getRegistrationForm, register } from '#lib/remote/registration.remote.js';

	const { countries } = $derived(await getRegistrationForm());

	// Instance of this visit (`.for`): what was typed does not come back on the next one.
	const form = register.for('inscription');
	const fields = form.fields;

	/** Step and errors decided in the browser; null = the server's answer applies. */
	let local = $state<{ step: number; errors: FieldErrors } | null>(null);
	let heading = $state<HTMLElement>();

	/** Page to come back to (?redirect=, a path of the site only), carried by the form up to the confirmation. */
	const back = $derived(safeReturnPath(page.url.searchParams.get(RETURN_PARAM)));
	const loginHref = $derived(back ? `${LOGIN_PATH}?${new URLSearchParams({ [RETURN_PARAM]: back })}` : LOGIN_PATH);

	/** Values shown until edited: the answer of a step posted without JS, else the defaults (profile of ?profil=pro|ecom). */
	const values = $derived(form.result?.values ?? { ...emptyValues(), profile: presetProfile(page.url.searchParams.get(PROFILE_PARAM)) });
	const issues = $derived(fields.allIssues() ?? []);
	/** Server errors (validation of a step without JS, API 422), first message of each field. */
	const serverErrors = $derived.by(() => {
		const byField: FieldErrors = {};
		for (const { path, message } of issues) if (typeof path[0] === 'string') byField[path[0] as FieldName] ??= message;
		return byField;
	});
	const error = $derived(fields.issues()?.[0]?.message);
	const step = $derived(local?.step ?? issueStep(issues.map(({ path }) => path[0])) ?? form.result?.step ?? 1);
	const errors: FieldErrors = $derived(local?.errors ?? serverErrors);
	const current = $derived(STEPS[step - 1]);

	const profile = $derived(fields.profile.value() || values.profile);
	// Read once: an unchecked box is not posted, so after a failed submission without JS its state is the
	// posted one; afterwards what the visitor ticks (`dirty`).
	const shippingSameInitial = form.result?.values.shippingSame ?? (fields.allIssues() ? Boolean(fields.shippingSame.value()) : true);
	const shippingSame = $derived(fields.shippingSame.dirty() ? Boolean(fields.shippingSame.value()) : shippingSameInitial);

	async function show(next: number, nextErrors: FieldErrors = {}) {
		local = { step: next, errors: nextErrors };
		await tick();
		heading?.scrollIntoView({ block: 'center' });
		const invalid = document.querySelector<HTMLElement>('.yack-register-card fieldset:not([hidden]) .is-invalid');
		(invalid ?? heading)?.focus();
	}

	const enhanced = form.enhance(async ({ submit }) => {
		const intent = fields.intent.value();
		if (intent === 'precedent') return show(Math.max(step - 1, 1));
		const typed = toValues({ ...values, ...fields.value(), shippingSame });
		const invalid = firstInvalidStep(typed, intent === 'creer' ? STEP_COUNT : step);
		if (invalid) return show(invalid.step, invalid.errors);
		if (intent !== 'creer') return show(step + 1);
		local = null;
		await submit();
	});
</script>

<svelte:head>
	<title>Créer un compte — {current.label} | Clik&amp;Yack</title>
	<meta name="robots" content="noindex" />
</svelte:head>

{#snippet countrySelect(name: 'billingCountryCode' | 'shippingCountryCode')}
	<div class="field required">
		<label for={name} class="form-label required">Pays</label>
		<select {...fields[name].as('select', values[name])} id={name} class="form-select" class:is-invalid={!!errors[name]} aria-invalid={errors[name] ? 'true' : undefined}>
			<option value="">Sélectionner</option>
			{#each countries as country (country.code)}
				<option value={country.code}>{country.name}</option>
			{/each}
		</select>
		{#if errors[name]}<div class="invalid-feedback d-block" data-test-field-error={name}>{errors[name]}</div>{/if}
	</div>
{/snippet}

<div class="container py-5" data-test-yack-register={current.name}>
	<div class="text-center mb-4">
		<h1 class="mb-2">Créer votre compte Clik&amp;Yack</h1>
		<p class="text-body-secondary mb-0">Déjà client ? <a href={loginHref}>Connectez-vous</a></p>
	</div>

	<Progress steps={STEPS.map((s) => ({ name: s.name, label: s.label }))} active={step} ariaLabel="Étapes de l'inscription" onselect={(position) => show(position)} />

	<div class="card yack-register-card shadow-sm">
		<div class="card-body p-4 p-lg-5">
			<h2 class="h4 mb-4" tabindex="-1" bind:this={heading}>Étape {step} sur {STEP_COUNT} — {current.label}</h2>

			{#if error}
				<div class="alert alert-danger" role="alert" data-test-register-error>{error}</div>
			{/if}

			<form {...enhanced} novalidate data-test-register-form={current.name}>
				<input {...fields.step.as('hidden', String(step))} />
				<input {...fields.back.as('hidden', back ?? '')} />

				<fieldset hidden={step !== 1}>
					<legend class="visually-hidden">Vous êtes</legend>
					<div class="row g-3 mb-2" role="radiogroup">
						<div class="col-md-6">
							<input {...fields.profile.as('radio', 'pro', values.profile === 'pro')} class="btn-check" id="profile-pro" />
							<label for="profile-pro" class="yack-profile-choice card h-100 border p-4">
								<span class="h5 mb-2">Professionnel (PRO)</span>
								<span class="text-body-secondary">Installateur, chauffagiste, frigoriste… Accès au SAV et aux pièces par n° de série ; ouverture à la commande en ligne sur demande.</span>
							</label>
						</div>
						<div class="col-md-6">
							<input {...fields.profile.as('radio', 'ecom', values.profile === 'ecom')} class="btn-check" id="profile-ecom" />
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
							<Field
								field={fields.companyName}
								id="companyName"
								label="Raison sociale"
								value={values.companyName}
								error={errors.companyName ?? null}
								required={profile === 'pro'}
								autocomplete="organization"
							/>
						</div>
						<div class="col-md-6">
							<Field
								field={fields.siret}
								id="siret"
								label="SIRET"
								value={values.siret}
								error={errors.siret ?? null}
								required={profile === 'pro'}
								help="14 chiffres, sans espaces."
								inputmode="numeric"
								maxlength={17}
								placeholder="123 456 789 00012"
							/>
						</div>
						<div class="col-md-6">
							<Field
								field={fields.vatNumber}
								id="vatNumber"
								label="N° de TVA intracommunautaire (facultatif)"
								value={values.vatNumber}
								error={errors.vatNumber ?? null}
								placeholder="FR00123456789"
							/>
						</div>
					</div>
				</fieldset>

				<fieldset hidden={step !== 3}>
					<legend class="visually-hidden">Contact</legend>
					<div class="row g-3">
						<div class="col-md-6">
							<Field field={fields.lastName} id="lastName" label="Nom" value={values.lastName} error={errors.lastName ?? null} required autocomplete="family-name" />
						</div>
						<div class="col-md-6">
							<Field field={fields.firstName} id="firstName" label="Prénom" value={values.firstName} error={errors.firstName ?? null} required autocomplete="given-name" />
						</div>
						<div class="col-md-7">
							<Field
								field={fields.email}
								id="email"
								type="email"
								label="E-mail"
								value={values.email}
								error={errors.email ?? null}
								required
								autocomplete="email"
								help="Il servira d'identifiant ; un lien d'activation y sera envoyé."
							/>
						</div>
						<div class="col-md-5">
							<Field
								field={fields.phone}
								id="phone"
								type="tel"
								label="Téléphone"
								value={values.phone}
								error={errors.phone ?? null}
								required
								autocomplete="tel"
								placeholder="04 42 00 00 00"
							/>
						</div>
						<div class="col-md-6">
							<Field
								field={fields.password}
								id="password"
								type="password"
								label="Mot de passe"
								value={values.password}
								error={errors.password ?? null}
								required
								autocomplete="new-password"
								help="8 caractères minimum."
							/>
						</div>
						<div class="col-md-6">
							<Field
								field={fields.passwordConfirm}
								id="passwordConfirm"
								type="password"
								label="Confirmation du mot de passe"
								value={values.passwordConfirm}
								error={errors.passwordConfirm ?? null}
								required
								autocomplete="new-password"
							/>
						</div>
					</div>
				</fieldset>

				<fieldset hidden={step !== 4}>
					<legend class="visually-hidden">Adresses</legend>
					<h3 class="h6 text-uppercase text-body-secondary mb-3">Adresse de facturation</h3>
					<div class="row g-3 mb-3">
						<div class="col-md-12">
							<Field
								field={fields.billingStreet}
								id="billingStreet"
								label="Adresse"
								value={values.billingStreet}
								error={errors.billingStreet ?? null}
								required
								autocomplete="billing street-address"
							/>
						</div>
						<div class="col-md-3">
							<Field
								field={fields.billingPostcode}
								id="billingPostcode"
								label="Code postal"
								value={values.billingPostcode}
								error={errors.billingPostcode ?? null}
								required
								autocomplete="billing postal-code"
							/>
						</div>
						<div class="col-md-5">
							<Field
								field={fields.billingCity}
								id="billingCity"
								label="Ville"
								value={values.billingCity}
								error={errors.billingCity ?? null}
								required
								autocomplete="billing address-level2"
							/>
						</div>
						<div class="col-md-4">{@render countrySelect('billingCountryCode')}</div>
					</div>
					<div class="form-check">
						<input {...fields.shippingSame.as('checkbox', shippingSameInitial)} class="form-check-input" id="shippingSame" data-test-shipping-same />
						<label class="form-check-label" for="shippingSame">Livrer à cette adresse</label>
					</div>
					<div class="mt-3" hidden={shippingSame} data-test-shipping-address>
						<h3 class="h6 text-uppercase text-body-secondary mb-3">Adresse de livraison</h3>
						<div class="row g-3">
							<div class="col-md-12">
								<Field
									field={fields.shippingStreet}
									id="shippingStreet"
									label="Adresse"
									value={values.shippingStreet}
									error={errors.shippingStreet ?? null}
									required
									autocomplete="shipping street-address"
								/>
							</div>
							<div class="col-md-3">
								<Field
									field={fields.shippingPostcode}
									id="shippingPostcode"
									label="Code postal"
									value={values.shippingPostcode}
									error={errors.shippingPostcode ?? null}
									required
									autocomplete="shipping postal-code"
								/>
							</div>
							<div class="col-md-5">
								<Field
									field={fields.shippingCity}
									id="shippingCity"
									label="Ville"
									value={values.shippingCity}
									error={errors.shippingCity ?? null}
									required
									autocomplete="shipping address-level2"
								/>
							</div>
							<div class="col-md-4">{@render countrySelect('shippingCountryCode')}</div>
						</div>
					</div>
				</fieldset>

				<!-- Primary button first in the DOM: Enter submits it, not "Étape précédente". -->
				<div class="d-flex flex-row-reverse justify-content-between align-items-center mt-4 pt-3 border-top">
					<button {...fields.intent.as('submit', step < STEP_COUNT ? 'etape' : 'creer')} class="btn btn-primary px-4" disabled={form.pending > 0} data-test-register-next>
						{step < STEP_COUNT ? 'Continuer' : 'Créer mon compte'}
					</button>
					{#if step > 1}
						<button {...fields.intent.as('submit', 'precedent')} class="btn btn-link px-0" data-test-register-previous>‹ Étape précédente</button>
					{:else}
						<span></span>
					{/if}
				</div>
			</form>
		</div>
	</div>
</div>
