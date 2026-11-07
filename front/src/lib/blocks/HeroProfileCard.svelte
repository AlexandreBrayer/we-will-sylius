<script lang="ts">
	// Hero side card (`card = profil`): what the viewer can do next, from the session profile
	// (getViewer, never cached across customers). Links: existing front routes only.
	import type { Profile } from '#lib/types.js';
	import { getYackContext } from './context.js';

	interface ProfileCard {
		title: string;
		text: string;
		primary: { label: string; url: string };
		secondary: { label: string; url: string };
	}

	const ctx = getYackContext();

	function profileCard(profile: Profile, firstName: string | null): ProfileCard {
		const hello = firstName ? `Bonjour ${firstName}` : 'Bonjour';
		switch (profile) {
			case 'pro':
				return {
					title: hello,
					text: 'Votre espace PRO : profil, adresses et demande d’ouverture à la commande en ligne.',
					primary: { label: 'Mon compte', url: '/compte' },
					secondary: { label: 'Accès e-commerce', url: '/compte/acces-commande' }
				};
			case 'ecom':
				return {
					title: hello,
					text: 'Vos prix, votre panier et le suivi de vos commandes.',
					primary: { label: 'Nos produits', url: '/produits' },
					secondary: { label: 'Suivre mes commandes', url: '/compte/commandes' }
				};
			default:
				return {
					title: 'Vous êtes installateur ?',
					text: 'Créez votre compte pro, puis demandez l’ouverture à la commande en ligne pour voir vos prix.',
					primary: { label: 'Créer mon compte pro', url: '/inscription' },
					secondary: { label: 'Me connecter', url: '/connexion' }
				};
		}
	}

	const card = $derived(profileCard(ctx.viewer.profile, ctx.viewer.firstName));

	// Builder canvas: no navigation out of the editor.
	function submit(event: SubmitEvent) {
		if (ctx.editor) event.preventDefault();
	}
</script>

<div class="yack-cms-hero-card" data-test-yack-hero-card={ctx.viewer.profile}>
	{#if ctx.editor}
		<p class="yack-cms-hero-card__note">Contenu selon le profil (aperçu visiteur)</p>
	{/if}
	<h2 class="h4 yack-cms-hero-card__title">{card.title}</h2>
	<p class="yack-cms-hero-card__text">{card.text}</p>
	<div class="d-flex flex-wrap align-items-center gap-3 mb-4">
		<a class="btn btn-primary" href={card.primary.url}>{card.primary.label}</a>
		<a class="link-primary fw-bold" href={card.secondary.url}>{card.secondary.label}</a>
	</div>
	<form class="input-group" method="get" action="/recherche" role="search" onsubmit={submit}>
		<input
			class="form-control"
			type="search"
			name="q"
			placeholder="Produit ou numéro de série"
			aria-label="Produit ou numéro de série"
		/>
		<button class="btn btn-outline-primary" type="submit">Chercher</button>
	</form>
</div>
