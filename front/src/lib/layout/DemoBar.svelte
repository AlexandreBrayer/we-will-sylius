<script lang="ts">
	// Barre de démonstration repliée par défaut : une petite languette rose collée en haut à gauche l'ouvre par-dessus
	// la page. Elle se replie seule après 3 s sans survol ni focus, au premier défilement, à chaque navigation et
	// sur Échap. Visiteur : fin de session. PRO ou ECOM : si personne n'est connecté, la page de connexion et ses
	// comptes de démo ; si un compte est connecté, bascule directe. Rendue seulement si YACK_DEMO_BAR=1 ; les droits
	// viennent de /me. Le menu « Nouveautés » liste les écrans nouveaux du site en deux colonnes, « Dans le devis » et
	// « En plus du devis » (options et pistes, avec leur pastille de statut et une légende), d'après demo-news.ts.
	import { afterNavigate } from '$app/navigation';
	import { page } from '$app/state';
	import { assistanceEnabled } from '#lib/remote/assistance.remote.js';
	import { switchProfile } from '#lib/remote/demo.remote.js';
	import type { Viewer } from '#lib/types.js';
	import { BACK_OFFICE_MARK, DEMO_NEWS_STATUSES, demoNewsByGroup, demoNewsFor, isBackOffice } from './demo-news.js';
	import { DEMO_PROFILES, demoAccountLine } from './demo-profiles.js';

	let { viewer }: { viewer: Viewer } = $props();

	/** Délai avant repli automatique quand ni la souris ni le focus ne sont dans la barre. */
	const AUTO_CLOSE_MS = 3000;

	const back = $derived(page.url.pathname + page.url.search);
	const loginUrl = $derived(`/connexion?redirect=${encodeURIComponent(page.url.pathname === '/connexion' ? '/' : back)}#comptes-demo`);
	const currentProfile = $derived(DEMO_PROFILES.find((p) => p.current(viewer)));
	const current = $derived(currentProfile?.label ?? 'Visiteur');
	const news = $derived(demoNewsFor(currentProfile?.key ?? 'visiteur', { assistance: await assistanceEnabled() }));
	const groups = $derived(demoNewsByGroup(news));

	let open = $state(false);
	let held = $state(false);
	let newsOpen = $state(false);
	let timer: ReturnType<typeof setTimeout> | undefined;
	let tab: HTMLButtonElement | undefined = $state();
	let bar: HTMLDivElement | undefined = $state();

	function close() {
		open = false;
		held = false;
		newsOpen = false;
		clearTimeout(timer);
	}

	/** Échap ou « Replier la barre de démo » : si le focus était dans la barre, il revient à la languette au lieu de se perdre. */
	function closeAndKeepFocus() {
		const inside = bar?.contains(document.activeElement) ?? false;
		close();
		if (inside) tab?.focus();
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
			if (event.key === 'Escape') closeAndKeepFocus();
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
	bind:this={tab}
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
	bind:this={bar}
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
						{#if p.account}<span class="yack-demo-bar__account">{demoAccountLine(p.account, p.accountNote)}</span>{/if}
						{#each p.otherAccounts ?? [] as other (other.account)}<span class="yack-demo-bar__account">{demoAccountLine(other.account, other.note)}</span>{/each}
					</span>
				</li>
			{/each}
		</ul>
		{#if news.length > 0}
			<div class="yack-demo-bar__news">
				<button
					type="button"
					class="yack-demo-bar__btn yack-demo-bar__news-btn"
					aria-expanded={newsOpen}
					aria-controls="yack-demo-news"
					onclick={() => (newsOpen = !newsOpen)}
					data-test-demo-news
				>
					Nouveautés <span class="yack-demo-bar__count">{news.length}</span>
					<svg viewBox="0 0 16 16" width="10" height="10" aria-hidden="true"><path d="M3 6l5 5 5-5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" /></svg>
				</button>
				<div class="yack-demo-bar__menu" id="yack-demo-news" hidden={!newsOpen}>
					{#each groups as { group, items } (group.key)}
						<div
							class="yack-demo-bar__group"
							role="group"
							aria-labelledby="yack-demo-news-{group.key}"
							aria-describedby={group.legend.length > 0 ? `yack-demo-news-${group.key}-legende` : undefined}
							data-test-demo-news-group={group.key}
						>
							<p class="yack-demo-bar__group-title" id="yack-demo-news-{group.key}">{group.title}</p>
							<ul class="yack-demo-bar__group-list">
								{#each items as item (item.href)}
									{@const backOffice = isBackOffice(item)}
									<li>
										<a
											href={item.href}
											target={backOffice ? '_blank' : undefined}
											rel={backOffice ? 'noopener' : undefined}
											data-test-demo-news-item={item.href}
										>
											{#if item.status}
												<span class="yack-demo-bar__status yack-demo-bar__status--{item.status}">{DEMO_NEWS_STATUSES[item.status].label}</span><span class="visually-hidden"> : </span>
											{/if}
											<span class="yack-demo-bar__entry">
												<span class="yack-demo-bar__entry-label">{item.label}</span>{#if item.hint}<span class="visually-hidden">, </span><small>{item.hint}</small>{/if}
											</span>
											{#if backOffice}
												<span class="yack-demo-bar__bo" title="Back-office, s’ouvre dans un nouvel onglet" aria-hidden="true">{BACK_OFFICE_MARK.label}</span><span class="visually-hidden"> ({BACK_OFFICE_MARK.meaning})</span>
											{/if}
										</a>
									</li>
								{/each}
							</ul>
							{#if group.legend.length > 0}
								<p class="yack-demo-bar__legend" id="yack-demo-news-{group.key}-legende">
									{#each group.legend as status, index (status)}<span class="yack-demo-bar__status yack-demo-bar__status--{status}">{DEMO_NEWS_STATUSES[status].label}</span> {DEMO_NEWS_STATUSES[status].meaning}{index < group.legend.length - 1 ? ' ; ' : '.'}{/each}
								</p>
							{/if}
						</div>
					{/each}
				</div>
			</div>
		{/if}
		<button type="button" class="yack-demo-bar__close" aria-label="Replier la barre de démo" onclick={closeAndKeepFocus}>
			<svg viewBox="0 0 16 16" width="12" height="12" aria-hidden="true"><path d="M3 10l5-5 5 5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" /></svg>
		</button>
	</div>
</div>
