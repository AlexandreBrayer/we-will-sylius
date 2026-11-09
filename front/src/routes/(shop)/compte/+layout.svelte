<script lang="ts">
	// Customer account chrome (Sylius shop account layout): breadcrumbs, side menu card (only the
	// entries that exist, the current one in magenta), page column (_account.scss).
	import { page } from '$app/state';
	import Breadcrumbs from '#lib/catalog/Breadcrumbs.svelte';
	import { getViewer } from '#lib/remote/session.remote.js';
	import type { Breadcrumb } from '#lib/types.js';

	let { children } = $props();

	const viewer = $derived(await getViewer());

	const TRAIL: Record<string, string> = {
		'/(shop)/compte/profil': 'Informations personnelles',
		'/(shop)/compte/adresses': "Carnet d'adresses",
		'/(shop)/compte/commandes': 'Historique des commandes',
		'/(shop)/compte/acces-commande': 'Commander en ligne'
	};

	const crumbs: Breadcrumb[] = $derived.by(() => {
		const route = page.route.id ?? '';
		const items: Breadcrumb[] = [
			{ label: 'Accueil', url: '/' },
			{ label: 'Mon compte', url: '/compte' }
		];
		const section = Object.keys(TRAIL).find((prefix) => route.startsWith(prefix));
		if (section) items.push({ label: TRAIL[section], url: section.replace('/(shop)', '') });
		if (route === '/(shop)/compte/adresses/nouvelle') items.push({ label: 'Ajouter une adresse', url: null });
		if (route === '/(shop)/compte/adresses/[id]') items.push({ label: "Modifier l'adresse", url: null });
		if (route === '/(shop)/compte/commandes/[number]') items.push({ label: `Commande n° ${page.params.number}`, url: null });
		items[items.length - 1] = { ...items[items.length - 1], url: null };
		return items;
	});

	const menu = $derived([
		{ href: '/compte', label: 'Tableau de bord' },
		{ href: '/compte/profil', label: 'Informations personnelles' },
		{ href: '/compte/profil#mot-de-passe', label: 'Changer votre mot de passe' },
		{ href: '/compte/adresses', label: "Carnet d'adresses" },
		{ href: '/compte/commandes', label: 'Historique des commandes' },
		...(viewer.canRequestOrderAccess ? [{ href: '/compte/acces-commande', label: 'Commander en ligne' }] : [])
	]);

	const isActive = (href: string) =>
		page.url.pathname === href || (href !== '/compte' && !href.includes('#') && page.url.pathname.startsWith(href));
	const name = $derived([viewer.firstName, viewer.lastName].filter(Boolean).join(' ') || viewer.email || 'Mon compte');
</script>

<div class="container mb-auto">
	<div class="row g-4 my-2">
		<div class="col-12">
			<Breadcrumbs items={crumbs} />
		</div>
		<div class="col-12 col-md-4 col-lg-3">
			<aside class="yack-account-nav">
				<div class="yack-account-nav__who">
					<span class="yack-account-nav__avatar" aria-hidden="true">{name.charAt(0)}</span>
					<div>
						<span class="yack-account-nav__name">{name}</span>
						<span class="yack-tag yack-tag--primary">{viewer.profileLabel}</span>
					</div>
				</div>
				<nav class="yack-account-nav__list" aria-label="Mon compte" data-test-account-menu>
					{#each menu as item (item.href)}
						<a class="yack-account-nav__link" class:is-active={isActive(item.href)} aria-current={isActive(item.href) ? 'page' : undefined} href={item.href}>{item.label}</a>
					{/each}
				</nav>
			</aside>
		</div>
		<div class="col-12 col-md-8 col-lg-9">
			{@render children()}
		</div>
	</div>
</div>
