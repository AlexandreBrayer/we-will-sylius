<script lang="ts">
	// Barre de démonstration repliée par défaut : une petite languette rose collée en haut à gauche l'ouvre par-dessus
	// la page. Elle se replie seule après 3 s sans survol ni focus, au premier défilement, à chaque navigation et
	// sur Échap. Visiteur : fin de session. PRO ou ECOM : si personne n'est connecté, la page de connexion et ses
	// comptes de démo ; si un compte est connecté, bascule directe. Rendue seulement si YACK_DEMO_BAR=1 ; les droits
	// viennent de /me.
	import { afterNavigate } from '$app/navigation';
	import { page } from '$app/state';
	import { switchProfile } from '#lib/remote/demo.remote.js';
	import type { Viewer } from '#lib/types.js';
	import { DEMO_PASSWORD, DEMO_PROFILES } from './demo-profiles.js';

	let { viewer }: { viewer: Viewer } = $props();

	/** Délai avant repli automatique quand ni la souris ni le focus ne sont dans la barre. */
	const AUTO_CLOSE_MS = 3000;

	const back = $derived(page.url.pathname + page.url.search);
	const loginUrl = $derived(`/connexion?redirect=${encodeURIComponent(page.url.pathname === '/connexion' ? '/' : back)}#comptes-demo`);
	const current = $derived(DEMO_PROFILES.find((p) => p.current(viewer))?.label ?? 'Visiteur');

	let open = $state(false);
	let held = $state(false);
	let timer: ReturnType<typeof setTimeout> | undefined;

	function close() {
		open = false;
		held = false;
		clearTimeout(timer);
	}

	function arm() {
		clearTimeout(timer);
		if (open && !held) timer = setTimeout(close, AUTO_CLOSE_MS);
	}

	function toggle() {
		open = !open;
		arm();
	}

	function hold(value: boolean) {
		held = value;
		arm();
	}

	afterNavigate(close);

	$effect(() => {
		if (!open) return;
		const startY = window.scrollY;
		const onScroll = () => {
			if (Math.abs(window.scrollY - startY) > 40) close();
		};
		const onKey = (event: KeyboardEvent) => {
			if (event.key === 'Escape') close();
		};
		window.addEventListener('scroll', onScroll, { passive: true });
		window.addEventListener('keydown', onKey);
		return () => {
			window.removeEventListener('scroll', onScroll);
			window.removeEventListener('keydown', onKey);
			clearTimeout(timer);
		};
	});
</script>

<button
	type="button"
	class="yack-demo-tab"
	class:is-open={open}
	aria-expanded={open}
	aria-controls="yack-demo-bar"
	aria-label="Démo : profil {current}. {open ? 'Replier' : 'Changer de profil'}"
	title="Démo : profil {current}"
	onclick={toggle}
	data-test-demo-tab
>
	<svg viewBox="0 0 16 16" width="12" height="12" aria-hidden="true"><path d="M3 6l5 5 5-5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" /></svg>
</button>

<div
	class="yack-demo-bar"
	class:is-open={open}
	id="yack-demo-bar"
	role="region"
	aria-label="Barre de démonstration"
	hidden={!open}
	onmouseenter={() => hold(true)}
	onmouseleave={() => hold(false)}
	onfocusin={() => hold(true)}
	onfocusout={() => hold(false)}
	data-test-demo-bar
>
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
		<button type="button" class="yack-demo-bar__close" aria-label="Replier la barre de démo" onclick={close}>
			<svg viewBox="0 0 16 16" width="12" height="12" aria-hidden="true"><path d="M3 10l5-5 5 5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" /></svg>
		</button>
	</div>
</div>
