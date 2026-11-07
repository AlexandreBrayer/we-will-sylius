<script lang="ts" module>
	/**
	 * Main menu of the target design (demo "Demain" mode, renderHeaderCible): entries and order of the
	 * maquette. Editorial entries lead to CMS pages (fixtures config/yack/fixtures_cms.yaml) and are
	 * shown only while their page is published, so no entry leads to a 404; a dropdown without any
	 * published page is hidden. "Nos produits", "Pièces détachées" and "Trouver un installateur" are
	 * routes of the front.
	 */
	interface MenuPage {
		label: string;
		slug: string;
	}

	type MenuEntry = { kind: 'dropdown'; key: string; label: string; items: MenuPage[] } | ({ kind: 'page' } & MenuPage);

	const MENU: MenuEntry[] = [
		{
			kind: 'dropdown',
			key: 'groupe',
			label: 'Le groupe',
			items: [
				{ label: 'Yack', slug: 'le-groupe' },
				{ label: 'Yack Smart Solutions', slug: 'yack-smart-solutions' },
				{ label: 'Yack Technical Institute', slug: 'yack-technical-institute' },
				{ label: 'Partenariats', slug: 'partenariats' },
				{ label: 'Politique RSE', slug: 'politique-rse' }
			]
		},
		{
			kind: 'dropdown',
			key: 'marques',
			label: 'Nos marques',
			items: [
				{ label: 'Yack', slug: 'marque-yack' },
				{ label: 'MHI', slug: 'marque-mhi' },
				{ label: 'RHOSS', slug: 'marque-rhoss' }
			]
		},
		{
			kind: 'dropdown',
			key: 'services',
			label: 'Nos services',
			items: [
				{ label: 'Nos prestations', slug: 'nos-services' },
				{ label: 'Nos formations', slug: 'yack-technical-institute' }
			]
		},
		{ kind: 'page', label: 'Catalogues', slug: 'catalogues' },
		// A CMS page; the "Nos documentations" space (route /documentation) is planned in lot 1.
		{ kind: 'page', label: 'Documentation', slug: 'nos-documentations' }
	];
</script>

<script lang="ts">
	// Shop header of the target design (Quentin's Figma, demo "Demain" mode): dark top bar (order
	// tracking, cart, account), main bar (logo, menu, search). Works without JavaScript: dropdowns
	// are <details>, the mobile menu and the search row open through their #anchor (:target).
	import { tick } from 'svelte';
	import { page } from '$app/state';
	import { logout } from '#lib/remote/session.remote.js';
	import type { CartSummary, Viewer } from '#lib/types.js';
	import Icon from './Icon.svelte';

	let {
		viewer,
		cart,
		published,
		onOpenCart
	}: {
		viewer: Viewer;
		/** Header cart widget, null when the viewer cannot order (visitor, PRO). */
		cart: CartSummary | null;
		/** Slugs of the published CMS pages (getPublishedPages). */
		published: string[];
		/** "Panier" click with JavaScript: opens the mini-cart (the link leads to /panier otherwise). */
		onOpenCart: (event: MouseEvent) => void;
	} = $props();

	const has = (slug: string) => published.includes(slug);
	const fullName = $derived([viewer.firstName, viewer.lastName].filter(Boolean).join(' '));
	const accountMeta = $derived([viewer.company, viewer.profileLabel].filter(Boolean).join(' · '));

	/** Open dropdown (one at a time), mobile menu, search row. */
	let openMenu = $state<string | null>(null);
	let menuOpen = $state(false);
	let searchOpen = $state(false);
	let searchInput = $state<HTMLInputElement>();

	const onToggle = (name: string) => (event: Event) => {
		const details = event.currentTarget as HTMLDetailsElement;
		if (details.open) openMenu = name;
		else if (openMenu === name) openMenu = null;
	};

	const current = (href: string) => page.url.pathname === href || page.url.pathname.startsWith(`${href}/`);

	async function toggleSearch(event: MouseEvent) {
		event.preventDefault();
		searchOpen = !searchOpen;
		if (searchOpen) {
			await tick();
			searchInput?.focus();
		}
	}

	function openMobileMenu(event: MouseEvent) {
		event.preventDefault();
		menuOpen = true;
	}

	function closeMobileMenu(event: MouseEvent) {
		event.preventDefault();
		menuOpen = false;
	}

	// Close menus on navigation; the search row stays open on the search page.
	$effect(() => {
		const path = page.url.pathname;
		openMenu = null;
		menuOpen = false;
		searchOpen = path === '/recherche';
	});
</script>

<svelte:window
	onclick={(event) => !(event.target as HTMLElement).closest('.yack-dd') && (openMenu = null)}
	onkeydown={(event) => event.key === 'Escape' && ((openMenu = null), (menuOpen = false))}
/>

<header class="yack-header">
	<div class="yack-topbar">
		<div class="container yack-topbar__in">
			<a class="yack-topbar__link yack-topbar__track" href="/compte/commandes"><Icon name="truck" /> Suivi de commandes</a>
			<div class="yack-topbar__right">
				{#if cart}
					<a class="yack-topbar__link" href="/panier" data-test-cart-button aria-haspopup="dialog" onclick={onOpenCart}>
						<Icon name="cart" /> Panier
						{#if cart.itemsCount > 0}<span class="yack-topbar__count" data-test-cart-count>{cart.itemsCount}</span>{/if}
					</a>
				{/if}
				{#if viewer.authenticated}
					<details class="yack-dd yack-dd--end" open={openMenu === 'account'} ontoggle={onToggle('account')}>
						<summary class="yack-topbar__link" aria-label="Mon compte">
							<Icon name="user" />
							<span data-test-full-name>Bonjour {viewer.firstName ?? ''}</span>
							<Icon name="chevron" size="sm" />
						</summary>
						<div class="yack-dd__menu" data-test-account-menu>
							<div class="yack-dd__head">
								<strong>{fullName || viewer.email}</strong>
								<small data-test-account-profile>{accountMeta}</small>
							</div>
							<a class="yack-dd__item" href="/compte">Mon compte</a>
							{#if viewer.canOrder}<a class="yack-dd__item" href="/compte/commandes">Mes commandes</a>{/if}
							{#if viewer.canRequestOrderAccess}<a class="yack-dd__item" href="/compte/acces-commande">Accès e-commerce</a>{/if}
							<form {...logout.for('header')}>
								<button class="yack-dd__item" type="submit">Me déconnecter</button>
							</form>
						</div>
					</details>
				{:else}
					<span class="yack-topbar__link yack-topbar__auth">
						<Icon name="user" />
						<a href="/connexion">Connexion</a><span aria-hidden="true">/</span><a href="/inscription">Inscription</a>
					</span>
				{/if}
			</div>
		</div>
	</div>

	<div class="yack-mainbar">
		<div class="container yack-mainbar__in">
			<a class="yack-mainbar__burger" href="#yack-nav" aria-controls="yack-nav" aria-expanded={menuOpen} aria-label="Ouvrir le menu" onclick={openMobileMenu}>
				<Icon name="menu" size="md" />
			</a>
			<a href="/" class="yack-mainbar__logo" aria-label="Yack, accueil">
				<img src="/yack/logo-yack.png" alt="Yack, Clik&amp;Yack" class="yack-logo" width="1146" height="1031" />
			</a>

			<nav id="yack-nav" class="yack-nav offcanvas-xl offcanvas-start" class:show={menuOpen} aria-label="Navigation principale">
				<div class="offcanvas-header">
					<span class="offcanvas-title fs-5">Menu</span>
					<a href="#top" class="btn-close" aria-label="Fermer le menu" onclick={closeMobileMenu}></a>
				</div>
				<div class="offcanvas-body yack-nav__body" data-test-menu>
					<a class="yack-nav__products" href="/produits" data-test-menu-item="Nos produits" aria-current={current('/produits') ? 'page' : undefined}>
						<span class="yack-nav__dot"><Icon name="menu" size="sm" /></span>Nos produits
					</a>
					{#each MENU as entry (entry.kind === 'page' ? entry.slug : entry.key)}
						{#if entry.kind === 'page'}
							{#if has(entry.slug)}
								<a class="yack-nav__link" href="/{entry.slug}" data-test-menu-item={entry.label} aria-current={current(`/${entry.slug}`) ? 'page' : undefined}>{entry.label}</a>
							{/if}
						{:else}
							{@const items = entry.items.filter((item) => has(item.slug))}
							{#if items.length > 0}
								<details class="yack-dd" open={openMenu === entry.key} ontoggle={onToggle(entry.key)}>
									<summary class="yack-nav__link" data-test-menu-item={entry.label}>{entry.label}</summary>
									<div class="yack-dd__menu">
										{#each items as item (item.slug + item.label)}
											<a class="yack-dd__item" href="/{item.slug}" aria-current={current(`/${item.slug}`) ? 'page' : undefined}>{item.label}</a>
										{/each}
									</div>
								</details>
							{/if}
						{/if}
					{/each}
					<a class="yack-nav__link yack-nav__link--new" href="/pieces" aria-current={current('/pieces') ? 'page' : undefined}>
						Pièces détachées <span class="yack-nav__badge">Nouveau</span>
					</a>
					<a class="yack-nav__link yack-nav__link--new" href="/trouver-un-installateur" aria-current={current('/trouver-un-installateur') ? 'page' : undefined}>
						Trouver un installateur <span class="yack-nav__badge">Nouveau</span>
					</a>
				</div>
			</nav>
			{#if menuOpen}
				<div class="offcanvas-backdrop fade show d-xl-none" role="presentation" onclick={() => (menuOpen = false)}></div>
			{/if}

			<a class="yack-mainbar__search" class:is-active={searchOpen} href="#yack-header-search" aria-controls="yack-header-search" aria-expanded={searchOpen} aria-label="Rechercher" onclick={toggleSearch}>
				<Icon name="search" size="md" />
			</a>
		</div>
	</div>

	<div id="yack-header-search" class="yack-header__search" class:is-open={searchOpen}>
		<div class="container">
			<form class="yack-search input-group" method="get" action="/recherche" role="search">
				<input
					bind:this={searchInput}
					class="form-control"
					type="search"
					name="q"
					value={page.url.pathname === '/recherche' ? (page.url.searchParams.get('q') ?? '') : ''}
					placeholder="Rechercher un produit, une référence…"
					aria-label="Rechercher"
				/>
				<button class="btn btn-primary" type="submit">Rechercher</button>
			</form>
		</div>
	</div>
</header>
