<script lang="ts">
	import { page } from '$app/state';
</script>

<div class="container py-6 text-center">
	<h1 class="display-6 fw-bold mb-3">{page.status === 404 ? 'Page introuvable' : 'Une erreur est survenue'}</h1>
	<p class="mb-4 text-body-secondary">{page.error?.message}</p>
	<a class="btn btn-primary" href="/accueil">Retour à l'accueil</a>
</div>
