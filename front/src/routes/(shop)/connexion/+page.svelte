<script lang="ts">
	import { page } from '$app/state';
	import AuthPanel from '#lib/layout/AuthPanel.svelte';
	import DemoAccounts from '#lib/layout/DemoAccounts.svelte';
	import { getDemoBar } from '#lib/remote/demo.remote.js';
	import { login } from '#lib/remote/session.remote.js';

	// Field errors (empty input) and login refusal (`invalid(…)`), first one shown.
	const error = $derived(login.fields.allIssues()?.[0]?.message);
	// Après une connexion depuis les comptes de démo : même retour que le formulaire (?redirect=).
	const back = $derived(page.url.searchParams.get('redirect') ?? '/');
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
					{#if error}
						<div class="alert alert-danger" role="alert" data-test-login-error>{error}</div>
					{:else if page.url.searchParams.has('expire')}
						<div class="alert alert-warning" role="status">Votre session a expiré : reconnectez-vous.</div>
					{/if}
					<form {...login}>
						<div class="mb-5">
							<div class="field mb-3 required">
								<label for="email" class="form-label required">Identifiant</label>
								<input {...login.fields.email.as('email')} id="email" required autocomplete="username" class="form-control" data-test-login-username />
							</div>
							<div class="field mb-3 required">
								<label for="password" class="form-label required">Mot de passe</label>
								<input {...login.fields._password.as('password')} id="password" required autocomplete="current-password" class="form-control" data-test-login-password />
							</div>
							<div class="form-check">
								<input {...login.fields.remember.as('checkbox')} id="remember" class="form-check-input" data-test-remember-me />
								<label class="form-check-label" for="remember">Rester connecté</label>
							</div>
						</div>
						<div class="d-grid mb-2">
							<button type="submit" class="btn btn-primary" disabled={login.pending > 0} data-test-button="login-button">Connexion</button>
						</div>
					</form>
					<div class="d-grid">
						<a class="btn btn-link" href="/mot-de-passe/oublie">Mot de passe oublié ?</a>
					</div>
					{#if await getDemoBar()}
						<DemoAccounts {back} />
					{/if}
				</div>
			</div>
		</div>
		<div class="col-12 col-sm-10 offset-sm-1 col-md-8 offset-md-2 col-lg-6 offset-lg-0 order-lg-0">
			<AuthPanel />
		</div>
	</div>
</div>
