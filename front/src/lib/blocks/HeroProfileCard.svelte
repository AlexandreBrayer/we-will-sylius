<script lang="ts">
	// Hero side card (`card = profil`): what the viewer can do next, from the session profile
	// (getViewer, never cached across customers). Links: existing front routes only.
	import Icon from '#lib/layout/Icon.svelte';
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
	<div class="yack-cms-hero-card__top">
		{#if ctx.editor}
			<p class="yack-cms-hero-card__note">Contenu selon le profil (aperçu visiteur)</p>
		{/if}
		<h2 class="yack-cms-hero-card__title">{card.title}</h2>
		<p class="yack-cms-hero-card__text">{card.text}</p>
		<div class="yack-cms-hero-card__actions">
			<a class="btn btn-primary" href={card.primary.url}>{card.primary.label}</a>
			<a class="btn btn-secondary" href={card.secondary.url}>{card.secondary.label}</a>
		</div>
	</div>
	<div class="yack-cms-hero-card__notch" aria-hidden="true"></div>
	<form class="yack-cms-hero-card__search" method="get" action="/recherche" role="search" onsubmit={submit}>
		<label class="yack-cms-hero-card__label" for="yack-hero-search">Recherche</label>
		<div class="yack-cms-hero-card__row">
			<input id="yack-hero-search" class="form-control" type="search" name="q" placeholder="Produit ou numéro de série" />
			<button class="btn btn-primary yack-btn-icon" type="submit"><span class="yack-btn-icon__dot"><Icon name="search" /></span>Recherche</button>
		</div>
	</form>
</div>
