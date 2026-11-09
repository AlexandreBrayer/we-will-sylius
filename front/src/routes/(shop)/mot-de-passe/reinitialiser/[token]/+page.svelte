<script lang="ts">
	// Link of the "mot de passe oublié" e-mail.
	import Field from '#lib/account/Field.svelte';
	import { resetPassword } from '#lib/remote/registration.remote.js';

	let { params } = $props();
	// One instance per link (`.for`).
	const form = $derived(resetPassword.for(params.token));
	const error = $derived(form.fields.issues()?.[0]?.message);
</script>

<svelte:head>
	<title>Nouveau mot de passe | Clik&amp;Yack</title>
	<meta name="robots" content="noindex" />
</svelte:head>

<div class="container py-5">
	<div class="card yack-auth-card shadow-sm">
		<div class="card-body p-4 p-lg-5">
			<h1 class="h3 mb-4">Choisir un nouveau mot de passe</h1>
			{#if form.result}
				<div class="alert alert-success" role="status" data-test-password-reset-done>Votre mot de passe a été modifié. Vous pouvez vous connecter.</div>
				<a href="/connexion" class="btn btn-primary">Me connecter</a>
			{:else}
				{#if error}
					<div class="alert alert-danger" role="alert">
						{error} <a href="/mot-de-passe/oublie">Mot de passe oublié</a>
					</div>
				{/if}
				<form {...form} novalidate>
					<input {...form.fields.token.as('hidden', params.token)} />
					<div class="mb-3">
						<Field
							field={form.fields._newPassword}
							id="newPassword"
							type="password"
							label="Nouveau mot de passe"
							required
							autocomplete="new-password"
							help="8 caractères minimum."
						/>
					</div>
					<div class="mb-4">
						<Field field={form.fields._confirmNewPassword} id="confirmNewPassword" type="password" label="Confirmation" required autocomplete="new-password" />
					</div>
					<button type="submit" class="btn btn-primary" disabled={form.pending > 0} data-test-password-reset-submit>Enregistrer</button>
				</form>
			{/if}
		</div>
	</div>
</div>
