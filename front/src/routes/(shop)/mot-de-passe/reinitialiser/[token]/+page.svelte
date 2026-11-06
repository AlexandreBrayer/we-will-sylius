<script lang="ts">
	import { enhance } from '$app/forms';
	import Field from '#lib/account/Field.svelte';

	let { form } = $props();
	let submitting = $state(false);
	const errors: Record<string, string> = $derived((form && 'errors' in form ? form.errors : null) ?? {});
</script>

<svelte:head>
	<title>Nouveau mot de passe | Clik&amp;Yack</title>
	<meta name="robots" content="noindex" />
</svelte:head>

<div class="container py-5">
	<div class="card yack-auth-card shadow-sm">
		<div class="card-body p-4 p-lg-5">
			<h1 class="h3 mb-4">Choisir un nouveau mot de passe</h1>
			{#if form && 'reset' in form}
				<div class="alert alert-success" role="status" data-test-password-reset-done>Votre mot de passe a été modifié. Vous pouvez vous connecter.</div>
				<a href="/connexion" class="btn btn-primary">Me connecter</a>
			{:else}
				{#if form?.error}
					<div class="alert alert-danger" role="alert">
						{form.error} <a href="/mot-de-passe/oublie">Mot de passe oublié</a>
					</div>
				{/if}
				<form
					method="POST"
					novalidate
					use:enhance={() => {
						submitting = true;
						return async ({ update }) => {
							await update();
							submitting = false;
						};
					}}
				>
					<div class="mb-3">
						<Field name="newPassword" type="password" label="Nouveau mot de passe" error={errors.newPassword} required autocomplete="new-password" help="8 caractères minimum." />
					</div>
					<div class="mb-4">
						<Field name="confirmNewPassword" type="password" label="Confirmation" error={errors.confirmNewPassword} required autocomplete="new-password" />
					</div>
					<button type="submit" class="btn btn-primary" disabled={submitting} data-test-password-reset-submit>Enregistrer</button>
				</form>
			{/if}
		</div>
	</div>
</div>
