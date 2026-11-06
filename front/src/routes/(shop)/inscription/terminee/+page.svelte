<script lang="ts">
	// Port of templates/shop/yack/account/register/done.html.twig.
	import Progress from '#lib/layout/Progress.svelte';
	import { getRegistered } from '#lib/remote/registration.remote.js';

	const data = $derived(await getRegistered());
	const steps = ['Profil', 'Société', 'Contact', 'Adresses', 'Activation'].map((label) => ({ label }));
</script>

<svelte:head>
	<title>Compte créé | Clik&amp;Yack</title>
	<meta name="robots" content="noindex" />
</svelte:head>

<div class="container py-5" data-test-yack-register="done">
	<Progress {steps} active={5} linksEnabled={false} ariaLabel="Étapes de l'inscription" />

	<div class="card yack-auth-card shadow-sm">
		<div class="card-body p-4 p-lg-5 text-center">
			<div class="display-6 mb-3" aria-hidden="true">✉</div>
			<h1 class="h3 mb-3">Votre compte est créé</h1>
			<p>
				Un e-mail d'activation a été envoyé à <strong data-test-register-email>{data.email}</strong>.<br />Cliquez sur le lien qu'il contient pour
				activer votre compte, puis connectez-vous.
			</p>
			{#if data.pro}
				<p class="text-body-secondary">
					Compte professionnel : vous aurez accès au SAV et aux pièces détachées. Pour commander en ligne, faites une demande depuis votre espace client.
				</p>
			{/if}
			<p class="small text-body-secondary mb-4">Rien reçu ? Vérifiez vos courriers indésirables.</p>
			<a href="/connexion" class="btn btn-primary">Aller à la connexion</a>
		</div>
	</div>
</div>
