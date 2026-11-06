<script lang="ts">
	import { enhance } from '$app/forms';
	import AuthPanel from '#lib/layout/AuthPanel.svelte';

	let { data, form } = $props();
	let submitting = $state(false);
</script>

<svelte:head>
	<title>Connexion | Clik&amp;Yack</title>
	<meta name="robots" content="noindex" />
</svelte:head>

<div class="container my-auto">
	<div class="row my-4">
		<div class="col-12 col-sm-10 offset-sm-1 col-md-8 offset-md-2 col-lg-6 offset-lg-0 col-xl-4 offset-xl-1 order-lg-1">
			<div class="d-flex justify-content-center align-items-center h-100 px-3">
				<div class="w-100 py-lg-5 mb-5 my-lg-5">
					<h1 class="h2 mb-5">Connexion</h1>
					<p class="text-body-secondary mt-n4 mb-4" data-test-yack-login-subtitle>
						Espace client Clik&amp;Yack : suivi de commandes, SAV et pièces détachées. Identifiant = votre adresse e-mail.
						<span class="d-block small mt-2">Compte tout juste créé ? Activez-le d'abord avec le lien reçu par e-mail.</span>
					</p>
					{#if form?.error}
						<div class="alert alert-danger" role="alert" data-test-login-error>{form.error}</div>
					{:else if data.expired}
						<div class="alert alert-warning" role="status">Votre session a expiré : reconnectez-vous.</div>
					{/if}
					<form
						method="POST"
						use:enhance={() => {
							submitting = true;
							return async ({ update }) => {
								await update();
								submitting = false;
							};
						}}
					>
						<div class="mb-5">
							<div class="field mb-3 required">
								<label for="email" class="form-label required">Identifiant</label>
								<input type="email" id="email" name="email" required autocomplete="username" class="form-control" defaultValue={form?.email ?? ''} data-test-login-username />
							</div>
							<div class="field mb-3 required">
								<label for="password" class="form-label required">Mot de passe</label>
								<input type="password" id="password" name="password" required autocomplete="current-password" class="form-control" data-test-login-password />
							</div>
							<div class="form-check">
								<input type="checkbox" id="remember" name="remember" value="1" class="form-check-input" data-test-remember-me />
								<label class="form-check-label" for="remember">Rester connecté</label>
							</div>
						</div>
						<div class="d-grid mb-2">
							<button type="submit" class="btn btn-primary" disabled={submitting} data-test-button="login-button">Connexion</button>
						</div>
					</form>
					<div class="d-grid">
						<a class="btn btn-link" href="/mot-de-passe/oublie">Mot de passe oublié ?</a>
					</div>
				</div>
			</div>
		</div>
		<div class="col-12 col-sm-10 offset-sm-1 col-md-8 offset-md-2 col-lg-6 offset-lg-0 order-lg-0">
			<AuthPanel />
		</div>
	</div>
</div>
