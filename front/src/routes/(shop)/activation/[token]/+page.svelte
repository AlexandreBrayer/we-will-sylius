<script lang="ts">
	let { data } = $props();
</script>

<svelte:head>
	<title>Activation du compte | Clik&amp;Yack</title>
	<meta name="robots" content="noindex" />
</svelte:head>

<div class="container py-5">
	<div class="card yack-auth-card shadow-sm" data-test-activation={data.activated ? 'ok' : 'error'}>
		<div class="card-body p-4 p-lg-5 text-center">
			{#if data.activated}
				<div class="display-6 mb-3" aria-hidden="true">✓</div>
				<h1 class="h3 mb-3">Votre compte est activé</h1>
				<p class="mb-4">Bienvenue chez Clik&amp;Yack ! Connectez-vous avec votre adresse e-mail et le mot de passe choisi à l'inscription.</p>
				<a href="/connexion" class="btn btn-primary">Me connecter</a>
			{:else}
				<h1 class="h3 mb-3">Activation impossible</h1>
				<p class="mb-2">{data.error}</p>
				<p class="text-body-secondary mb-4">Votre compte est peut-être déjà actif : essayez de vous connecter, ou demandez un nouveau mot de passe.</p>
				<div class="d-flex flex-wrap justify-content-center gap-2">
					<a href="/connexion" class="btn btn-primary">Me connecter</a>
					<a href="/mot-de-passe/oublie" class="btn btn-outline-gray">Mot de passe oublié</a>
				</div>
			{/if}
		</div>
	</div>
</div>
