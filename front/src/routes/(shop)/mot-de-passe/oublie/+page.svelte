<script lang="ts">
	import AuthPanel from '#lib/layout/AuthPanel.svelte';
	import { requestPasswordReset } from '#lib/remote/registration.remote.js';

	// Instance of this visit (`.for`): the confirmation does not come back on the next one.
	const form = requestPasswordReset.for('oublie');
	const error = $derived(form.fields.allIssues()?.[0]?.message);
</script>

<svelte:head>
	<title>Mot de passe oublié | Clik&amp;Yack</title>
	<meta name="robots" content="noindex" />
</svelte:head>

<div class="container my-auto">
	<div class="row my-4">
		<div class="col-12 col-sm-10 offset-sm-1 col-md-8 offset-md-2 col-lg-6 offset-lg-0 col-xl-4 offset-xl-1 order-lg-1">
			<div class="d-flex justify-content-center align-items-center h-100 px-3">
				<div class="w-100 py-lg-5 mb-5 my-lg-5">
					<h1 class="h2 mb-5">Mot de passe oublié</h1>
					{#if form.result}
						<div class="alert alert-success" role="status" data-test-reset-requested>
							Si un compte existe pour <strong>{form.result.sent}</strong>, un e-mail contenant un lien de réinitialisation vient de lui être envoyé.
							Le lien permet de choisir un nouveau mot de passe.
						</div>
						<a class="btn btn-primary" href="/connexion">Retour à la connexion</a>
					{:else}
						<p class="text-body-secondary mt-n4 mb-4">Indiquez l'adresse e-mail de votre compte : nous vous envoyons un lien pour choisir un nouveau mot de passe.</p>
						{#if error}
							<div class="alert alert-danger" role="alert">{error}</div>
						{/if}
						<form {...form} novalidate>
							<div class="field mb-4 required">
								<label for="email" class="form-label required">E-mail</label>
								<input {...form.fields.email.as('email')} id="email" required autocomplete="email" class="form-control" data-test-reset-email />
							</div>
							<div class="d-grid mb-2">
								<button type="submit" class="btn btn-primary" disabled={form.pending > 0} data-test-reset-submit>Réinitialiser</button>
							</div>
						</form>
						<div class="d-grid">
							<a class="btn btn-link" href="/connexion">Retour à la connexion</a>
						</div>
					{/if}
				</div>
			</div>
		</div>
		<div class="col-12 col-sm-10 offset-sm-1 col-md-8 offset-md-2 col-lg-6 offset-lg-0 order-lg-0">
			<AuthPanel />
		</div>
	</div>
</div>
