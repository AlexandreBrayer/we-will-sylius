<script lang="ts">
	// Barre de démonstration, au-dessus de l'en-tête comme la barre noire de la maquette. Visiteur : fin de session.
	// PRO ou ECOM : si personne n'est connecté, la page de connexion et ses comptes de démo (on voit où se connecter) ;
	// si un compte est connecté, bascule directe. Rendue seulement si YACK_DEMO_BAR=1 ; les droits viennent de /me.
	import { page } from '$app/state';
	import { switchProfile } from '#lib/remote/demo.remote.js';
	import type { Viewer } from '#lib/types.js';
	import { DEMO_PASSWORD, DEMO_PROFILES } from './demo-profiles.js';

	let { viewer }: { viewer: Viewer } = $props();

	const back = $derived(page.url.pathname + page.url.search);
	const loginUrl = $derived(`/connexion?redirect=${encodeURIComponent(page.url.pathname === '/connexion' ? '/' : back)}#comptes-demo`);
</script>

<div class="yack-demo-bar" data-test-demo-bar>
	<div class="container yack-demo-bar__in">
		<span class="yack-demo-bar__label">Démo : naviguer en</span>
		<ul class="yack-demo-bar__list">
			{#each DEMO_PROFILES as p (p.key)}
				<li class="yack-demo-bar__item">
					{#if p.key === 'admin'}
						<a class="yack-demo-bar__btn" href="/admin/" target="_blank" rel="noopener" aria-describedby="yack-demo-tip-{p.key}" data-test-demo-profile={p.key}>{p.label}</a>
					{:else if p.key !== 'visiteur' && !viewer.authenticated}
						<a class="yack-demo-bar__btn" href={loginUrl} aria-describedby="yack-demo-tip-{p.key}" data-test-demo-profile={p.key}>{p.label}</a>
					{:else}
						{@const f = switchProfile.for(p.key)}
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
					{/if}
					<span class="yack-demo-bar__tip" role="tooltip" id="yack-demo-tip-{p.key}">
						<strong>Peut :</strong> {p.can}{#if p.cannot}<br /><strong>{p.key === 'admin' ? '' : 'Ne peut pas :'}</strong> {p.cannot}{/if}
						{#if p.account}<span class="yack-demo-bar__account">{p.account} / {DEMO_PASSWORD}</span>{/if}
					</span>
				</li>
			{/each}
		</ul>
	</div>
</div>
