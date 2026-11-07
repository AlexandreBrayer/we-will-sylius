<script lang="ts">
	// Barre de démonstration (DEMO-01), au-dessus de l'en-tête comme la barre noire de la maquette : une bascule par
	// profil sur les comptes de démo. Rendue seulement si YACK_DEMO_BAR=1 ; les droits affichés ensuite viennent de /me.
	import { page } from '$app/state';
	import { switchProfile } from '#lib/remote/demo.remote.js';
	import type { Viewer } from '#lib/types.js';

	let { viewer }: { viewer: Viewer } = $props();

	const PROFILES = [
		{ key: 'visiteur', label: 'Visiteur', current: (v: Viewer) => !v.authenticated },
		{ key: 'pro', label: 'PRO', current: (v: Viewer) => v.profile === 'pro' },
		{ key: 'ecom', label: 'ECOM', current: (v: Viewer) => v.profile === 'ecom' }
	] as const;

	const back = $derived(page.url.pathname + page.url.search);
</script>

<div class="yack-demo-bar" data-test-demo-bar>
	<div class="container yack-demo-bar__in">
		<span class="yack-demo-bar__label">Démo : naviguer en</span>
		<ul class="yack-demo-bar__list">
			{#each PROFILES as p (p.key)}
				{@const f = switchProfile.for(p.key)}
				<li>
					<form {...f}>
						<input {...f.fields.profile.as('hidden', p.key)} />
						<input {...f.fields.back.as('hidden', back)} />
						<button type="submit" class="yack-demo-bar__btn" class:is-current={p.current(viewer)} aria-pressed={p.current(viewer)} data-test-demo-profile={p.key}>
							{p.label}
						</button>
					</form>
				</li>
			{/each}
			<li>
				<a class="yack-demo-bar__btn" href="/admin/" target="_blank" rel="noopener" data-test-demo-profile="admin">Admin (back-office)</a>
			</li>
		</ul>
	</div>
</div>
