<script lang="ts">
	import Field from '#lib/account/Field.svelte';
	import { changePassword, getCustomer, updateProfile } from '#lib/remote/account.remote.js';
	import { getViewer } from '#lib/remote/session.remote.js';

	const [viewer, customer] = $derived(await Promise.all([getViewer(), getCustomer()]));

	// Instances of this visit (`.for`): their messages do not come back on the next one.
	const infos = updateProfile.for('profil');
	const password = changePassword.for('profil');
	// The saved values stay in the fields; the passwords are emptied after a success.
	const infosForm = infos.enhance(async ({ submit }) => void (await submit()));
	const passwordForm = password.enhance(async ({ submit, fields }) => {
		if (await submit()) fields.set({ _currentPassword: '', _newPassword: '', _confirmNewPassword: '' });
	});
</script>

<svelte:head>
	<title>Informations personnelles | Clik&amp;Yack</title>
	<meta name="robots" content="noindex" />
</svelte:head>

<div class="mb-4">
	<h1>Informations personnelles</h1>
	Modifier vos informations personnelles
</div>

<section class="mb-5" data-test-profile-infos>
	{#if infos.result?.saved}
		<div class="alert alert-success" role="status" data-test-profile-saved>Vos informations ont été enregistrées.</div>
	{/if}
	<form {...infosForm} novalidate>
		<div class="row g-3 mb-3">
			<div class="col-md-6">
				<Field field={infos.fields.lastName} id="lastName" label="Nom" value={customer.lastName} required autocomplete="family-name" />
			</div>
			<div class="col-md-6">
				<Field field={infos.fields.firstName} id="firstName" label="Prénom" value={customer.firstName} required autocomplete="given-name" />
			</div>
			<div class="col-md-7">
				<div class="field">
					<label for="email" class="form-label">E-mail</label>
					<input id="email" type="email" class="form-control" value={customer.email} readonly disabled aria-describedby="email-help" />
					<div class="form-text" id="email-help">Identifiant de connexion : contactez Yack pour le modifier.</div>
				</div>
			</div>
			<div class="col-md-5">
				<Field
					field={infos.fields.phoneNumber}
					id="phoneNumber"
					type="tel"
					label="Téléphone"
					value={customer.phoneNumber}
					autocomplete="tel"
					placeholder="04 42 00 00 00"
				/>
			</div>
		</div>
		<button type="submit" class="btn btn-primary" disabled={infos.pending > 0} data-test-profile-save>Enregistrer</button>
	</form>

	{#if viewer.company || viewer.siret}
		<div class="card bg-body-tertiary border-0 mt-4">
			<div class="card-body">
				<div class="fw-bold mb-2">Société</div>
				<dl class="row mb-0 small">
					{#if viewer.company}<dt class="col-sm-4 fw-normal text-body-secondary">Raison sociale</dt><dd class="col-sm-8">{viewer.company}</dd>{/if}
					{#if viewer.siret}<dt class="col-sm-4 fw-normal text-body-secondary">SIRET</dt><dd class="col-sm-8">{viewer.siret}</dd>{/if}
					{#if viewer.vatNumber}<dt class="col-sm-4 fw-normal text-body-secondary">N° TVA intracom.</dt><dd class="col-sm-8">{viewer.vatNumber}</dd>{/if}
				</dl>
				<div class="small text-body-secondary mt-2">Les informations de société sont vérifiées par Yack : contactez votre agence pour les modifier.</div>
			</div>
		</div>
	{/if}
</section>

<section id="mot-de-passe" class="pt-4 border-top" data-test-change-password>
	<h2 class="h4 mb-3">Changer votre mot de passe</h2>
	{#if password.result?.saved}
		<div class="alert alert-success" role="status" data-test-password-saved>Votre mot de passe a été modifié.</div>
	{/if}
	<form {...passwordForm} novalidate>
		<div class="row g-3 mb-3">
			<div class="col-md-12 col-lg-6">
				<Field field={password.fields._currentPassword} id="currentPassword" type="password" label="Mot de passe actuel" required autocomplete="current-password" />
			</div>
			<div class="w-100 m-0"></div>
			<div class="col-md-6">
				<Field
					field={password.fields._newPassword}
					id="newPassword"
					type="password"
					label="Nouveau mot de passe"
					required
					autocomplete="new-password"
					help="8 caractères minimum."
				/>
			</div>
			<div class="col-md-6">
				<Field field={password.fields._confirmNewPassword} id="confirmNewPassword" type="password" label="Confirmation" required autocomplete="new-password" />
			</div>
		</div>
		<button type="submit" class="btn btn-primary" disabled={password.pending > 0} data-test-password-save>Changer le mot de passe</button>
	</form>
</section>
