<script lang="ts">
	// Barre de démonstration, au-dessus de l'en-tête comme la barre noire de la maquette : une bascule par
	// profil sur les comptes de démo. Rendue seulement si YACK_DEMO_BAR=1 ; les droits affichés ensuite viennent de /me.
	import { page } from '$app/state';
	import { switchProfile } from '#lib/remote/demo.remote.js';
	import type { Viewer } from '#lib/types.js';

	let { viewer }: { viewer: Viewer } = $props();

	// Infobulles : la règle des profils du site actuel de Yack (Espace Pro, Espace E-commerce), reprise en V1.
	const PROFILES = [
		{
			key: 'visiteur',
			label: 'Visiteur',
			can: 'Parcourt le catalogue en vitrine.',
			cannot: 'Ni prix, ni panier, ni documentation technique.',
			account: '',
			current: (v: Viewer) => !v.authenticated
		},
		{
			key: 'pro',
			label: 'PRO',
			can: 'Installateur : catalogue, documentation technique (espace en cours d’intégration), catalogues PDF.',
			cannot: 'Ni prix, ni commande : il peut demander l’accès e-commerce depuis son compte.',
			account: 'pro@yack.test',
			current: (v: Viewer) => v.profile === 'pro'
		},
		{
			key: 'ecom',
			label: 'ECOM',
			can: 'Client e-commerce : ses prix HT, le stock, le panier, la commande et le paiement en ligne.',
			cannot: '',
			account: 'ecom@yack.test',
			current: (v: Viewer) => v.profile === 'ecom'
		}
	] as const;
	const ADMIN = {
		can: 'Équipe Yack : back-office (produits, commandes, comptes et demandes d’ouverture, pages du site).',
		cannot: 'S’ouvre dans un nouvel onglet ; la boutique reste dans le profil en cours.',
		account: 'admin@yack.test'
	};
	/** Mot de passe commun des comptes de démo (fixtures), affiché seulement dans la barre de démo. */
	const DEMO_PASSWORD = 'yack';

	const back = $derived(page.url.pathname + page.url.search);
</script>

<div class="yack-demo-bar" data-test-demo-bar>
	<div class="container yack-demo-bar__in">
		<span class="yack-demo-bar__label">Démo : naviguer en</span>
		<ul class="yack-demo-bar__list">
			{#each PROFILES as p (p.key)}
				{@const f = switchProfile.for(p.key)}
				<li class="yack-demo-bar__item">
					<form {...f}>
						<input {...f.fields.profile.as('hidden', p.key)} />
						<input {...f.fields.back.as('hidden', back)} />
						<button
							type="submit"
							class="yack-demo-bar__btn"
							class:is-current={p.current(viewer)}
							aria-pressed={p.current(viewer)}
							aria-describedby="yack-demo-tip-{p.key}"
							data-test-demo-profile={p.key}
						>
							{p.label}
						</button>
					</form>
					<span class="yack-demo-bar__tip" role="tooltip" id="yack-demo-tip-{p.key}">
						<strong>Peut :</strong> {p.can}{#if p.cannot}<br /><strong>Ne peut pas :</strong> {p.cannot}{/if}
						{#if p.account}<span class="yack-demo-bar__account">Connexion automatique au clic. À la main : {p.account} / {DEMO_PASSWORD}</span>{/if}
					</span>
				</li>
			{/each}
			<li class="yack-demo-bar__item">
				<a class="yack-demo-bar__btn" href="/admin/" target="_blank" rel="noopener" aria-describedby="yack-demo-tip-admin" data-test-demo-profile="admin">Admin (back-office)</a>
				<span class="yack-demo-bar__tip" role="tooltip" id="yack-demo-tip-admin">
					<strong>Peut :</strong> {ADMIN.can}<br />{ADMIN.cannot}
					<span class="yack-demo-bar__account">Connexion du back-office : {ADMIN.account} / {DEMO_PASSWORD}</span>
				</span>
			</li>
		</ul>
	</div>
</div>
