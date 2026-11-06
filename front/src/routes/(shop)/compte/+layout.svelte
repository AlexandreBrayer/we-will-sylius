<script lang="ts">
	// Customer account chrome (Sylius shop account layout): breadcrumbs, menu column, page column.
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
</script>

<div class="container mb-auto">
	<div class="row my-4">
		<div class="col-12">
			<Breadcrumbs items={crumbs} />
		</div>
		<div class="col-12 col-md-3 mb-4 mb-md-0">
			<div class="mb-3">
				<div class="h3 mb-4">Mon compte</div>
				<nav class="d-inline-flex flex-column" aria-label="Mon compte" data-test-account-menu>
					{#each menu as item (item.href)}
						<a
							class="d-flex align-items-center gap-2 py-1 link-reset"
							class:fw-bold={page.url.pathname === item.href || (item.href !== '/compte' && !item.href.includes('#') && page.url.pathname.startsWith(item.href))}
							href={item.href}>{item.label}</a
						>
					{/each}
				</nav>
			</div>
		</div>
		<div class="col-12 col-md-9">
			{@render children()}
		</div>
	</div>
</div>
