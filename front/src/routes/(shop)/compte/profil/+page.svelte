<script lang="ts">
	import { enhance, type SubmitFunction } from '$app/forms';
	import Field from '#lib/account/Field.svelte';
	import { getViewer } from '#lib/remote/session.remote.js';

	let { data, form } = $props();
	const viewer = $derived(await getViewer());
	let busy = $state<string | null>(null);

	const infos = $derived(form?.infos);
	const infoValues = $derived(infos?.values ?? data.customer);
	const infoErrors: Record<string, string> = $derived(infos?.errors ?? {});
	const passwordErrors: Record<string, string> = $derived(form?.password?.errors ?? {});

	/** Busy flag per form; the password fields are emptied after an answer. */
	const submit =
		(name: string): SubmitFunction =>
		() => {
			busy = name;
			return async ({ update }) => {
				await update({ reset: name === 'password' });
				busy = null;
			};
		};
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
	{#if infos && 'saved' in infos}
		<div class="alert alert-success" role="status" data-test-profile-saved>Vos informations ont été enregistrées.</div>
	{/if}
	<form method="POST" action="?/infos" novalidate use:enhance={submit('infos')}>
		<div class="row g-3 mb-3">
			<div class="col-md-6"><Field name="lastName" label="Nom" value={infoValues.lastName} error={infoErrors.lastName} required autocomplete="family-name" /></div>
			<div class="col-md-6"><Field name="firstName" label="Prénom" value={infoValues.firstName} error={infoErrors.firstName} required autocomplete="given-name" /></div>
			<div class="col-md-7">
				<Field name="email" type="email" label="E-mail" value={data.customer.email} readonly disabled help="Identifiant de connexion : contactez Yack pour le modifier." />
			</div>
			<div class="col-md-5">
				<Field name="phoneNumber" type="tel" label="Téléphone" value={infoValues.phoneNumber} error={infoErrors.phoneNumber} autocomplete="tel" placeholder="04 42 00 00 00" />
			</div>
		</div>
		<button type="submit" class="btn btn-primary" disabled={busy === 'infos'} data-test-profile-save>Enregistrer</button>
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
	{#if form?.password && 'saved' in form.password}
		<div class="alert alert-success" role="status" data-test-password-saved>Votre mot de passe a été modifié.</div>
	{/if}
	<form method="POST" action="?/motDePasse" novalidate use:enhance={submit('password')}>
		<div class="row g-3 mb-3">
			<div class="col-md-12 col-lg-6">
				<Field name="currentPassword" type="password" label="Mot de passe actuel" error={passwordErrors.currentPassword} required autocomplete="current-password" />
			</div>
			<div class="w-100 m-0"></div>
			<div class="col-md-6">
				<Field name="newPassword" type="password" label="Nouveau mot de passe" error={passwordErrors.newPassword} required autocomplete="new-password" help="8 caractères minimum." />
			</div>
			<div class="col-md-6">
				<Field name="confirmNewPassword" type="password" label="Confirmation" error={passwordErrors.confirmNewPassword} required autocomplete="new-password" />
			</div>
		</div>
		<button type="submit" class="btn btn-primary" disabled={busy === 'password'} data-test-password-save>Changer le mot de passe</button>
	</form>
</section>
