<script lang="ts">
	// Comptes de démonstration sur la page de connexion (barre de démo active) : profil, ce qu'il peut faire,
	// identifiants, et connexion en un clic sur le compte. `back` : page où revenir après la connexion.
	import { switchProfile } from '#lib/remote/demo.remote.js';
	import { DEMO_PASSWORD, DEMO_PROFILES } from './demo-profiles.js';

	let { back }: { back: string } = $props();

	const accounts = DEMO_PROFILES.filter((p) => p.account !== '');
</script>

<section class="yack-demo-accounts" id="comptes-demo" aria-labelledby="comptes-demo-titre" data-test-demo-accounts>
	<h2 class="yack-demo-accounts__title" id="comptes-demo-titre">Comptes de démonstration</h2>
	<p class="yack-demo-accounts__intro">Mot de passe commun : <code>{DEMO_PASSWORD}</code>. Un clic connecte le compte.</p>
	<ul class="yack-demo-accounts__list">
		{#each accounts as p (p.key)}
			<li class="yack-demo-accounts__item">
				<div>
					<strong>{p.label}</strong> <code>{p.account}</code>
					<p class="yack-demo-accounts__can">{p.can}{#if p.cannot && p.key !== 'admin'} <span>{p.cannot}</span>{/if}</p>
				</div>
				{#if p.key === 'admin'}
					<a class="btn btn-sm btn-outline-primary" href="/admin/" target="_blank" rel="noopener">Ouvrir le back-office</a>
				{:else}
					{@const f = switchProfile.for(`connexion-${p.key}`)}
					<form {...f}>
						<input {...f.fields.profile.as('hidden', p.key)} />
						<input {...f.fields.back.as('hidden', back)} />
						<button type="submit" class="btn btn-sm btn-primary" data-test-demo-login={p.key}>Se connecter en {p.label}</button>
					</form>
				{/if}
			</li>
		{/each}
	</ul>
</section>
