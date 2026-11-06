<script lang="ts">
	// Port of the Sylius shop header (templates/shop/yack/layout/* + Sylius base header hooks):
	// same markup and classes; dropdowns / mobile menu driven by Svelte instead of Bootstrap JS.
	import { page } from '$app/state';
	import { formatPrice } from '#lib/format.js';
	import { logout } from '#lib/remote/session.remote.js';
	import type { CartSummary, Viewer } from '#lib/types.js';
	import Icon from './Icon.svelte';

	let {
		viewer,
		cart,
		servicesPage
	}: {
		viewer: Viewer;
		/** Header cart widget, null when the viewer cannot order (visitor, PRO). */
		cart: CartSummary | null;
		/** The CMS page "nos-services" is published. */
		servicesPage: boolean;
	} = $props();

	const BADGES = { visitor: 'text-bg-light', pro: 'text-bg-warning', ecom: 'text-bg-success' } as const;

	let open = $state<string | null>(null);
	let menuOpen = $state(false);
	const toggle = (menu: string) => (open = open === menu ? null : menu);

	// Close menus on navigation.
	$effect(() => {
		void page.url.pathname;
		open = null;
		menuOpen = false;
	});
</script>

<svelte:window onclick={(event) => !(event.target as HTMLElement).closest('.dropdown') && (open = null)} />

<header>
	<div class="bg-black">
		<div class="container py-2 fs-7">
			<div class="row g-3 align-items-center">
				<div class="col-12 col-md">
					<a href="/">Clik&amp;Yack — la boutique des professionnels du génie climatique (POC Sylius)</a>
				</div>
				<div class="col-auto">
					<span class="badge rounded-pill {BADGES[viewer.profile]}" title="Profil commercial appliqué à la session" data-test-yack-profile={viewer.profile}>
						{viewer.profileLabel}
					</span>
				</div>
			</div>
		</div>
	</div>
	<div class="border-bottom py-4">
		<div class="container">
			<div class="row align-items-center">
				<div class="col-auto">
					<a href="/" class="d-inline-block" aria-label="Yack — accueil">
						<img src="/yack/logo-yack.png" alt="Yack — Clik&amp;Yack" class="yack-logo" width="1146" height="1031" />
					</a>
				</div>
				<div class="col-12 col-lg order-last order-lg-0 mt-3 mt-lg-0">
					<form class="yack-search input-group" method="get" action="/recherche" role="search">
						<input
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
				<div class="col-auto">
					{#if viewer.authenticated}
						<div class="d-lg-none dropdown">
							<button class="btn btn-icon btn-transparent px-0" aria-expanded={open === 'account'} aria-label="Mon compte" onclick={() => toggle('account')}>
								<Icon name="user" />
							</button>
							<ul class="dropdown-menu dropdown-menu-end" class:show={open === 'account'}>
								<li><a href="/compte" class="link-reset dropdown-item">Mon compte</a></li>
								<li>
									<form {...logout.for('menu')}>
										<button class="link-reset dropdown-item" type="submit">Déconnexion</button>
									</form>
								</li>
							</ul>
						</div>
						<div class="d-none d-lg-flex gap-2 align-items-center ps-2">
							<Icon name="user" />
							<span data-test-full-name>Bienvenue {viewer.firstName ?? ''}</span>
							<small aria-hidden="true" class="text-black px-1">|</small>
							<a href="/compte" class="link-reset">Mon compte</a>
							<small aria-hidden="true" class="text-black px-1">|</small>
							<form {...logout.for('header')} class="d-inline">
								<button class="btn btn-link link-reset p-0 border-0 align-baseline" type="submit">Déconnexion</button>
							</form>
						</div>
					{:else}
						<div class="d-flex align-items-center">
							<div class="d-lg-none">
								<a href="/connexion" class="btn btn-icon btn-transparent px-0" aria-label="Connexion"><Icon name="user" /></a>
							</div>
							<div class="d-none d-lg-flex align-items-center gap-2 ps-2">
								<Icon name="user" />
								<a href="/connexion" class="link-reset">Connexion</a>
								<small aria-hidden="true" class="text-black px-1">|</small>
								<a href="/inscription" class="link-reset">Créer un compte</a>
							</div>
						</div>
					{/if}
				</div>
				{#if cart}
					<div class="col-auto position-relative" data-test-cart-button>
						<a class="btn btn-icon btn-transparent px-0 position-relative" href="/panier" aria-label="Panier">
							<Icon name="bag" />
							{#if cart.itemsCount > 0}
								<span class="position-absolute top-0 start-0 translate-middle badge rounded-pill bg-primary" data-test-cart-count>{cart.itemsCount}</span>
							{/if}
							<span class="d-none d-lg-block ps-1" data-test-cart-total>{formatPrice(cart.itemsTotal)}</span>
						</a>
					</div>
				{/if}
				<div class="col-auto d-lg-none">
					<button class="navbar-toggler btn btn-icon btn-transparent px-0" type="button" aria-controls="navbarNav" aria-expanded={menuOpen} aria-label="Menu" onclick={() => (menuOpen = true)}>
						<Icon name="menu" size="md" />
					</button>
				</div>
			</div>
		</div>
	</div>
	<div class="w-100 border-bottom">
		<nav class="navbar offcanvas-lg offcanvas-start offcanvas-wide p-0" class:show={menuOpen} id="navbarNav" aria-label="Navigation principale">
			<div class="offcanvas-header w-full">
				<span class="offcanvas-title fs-5">Menu</span>
				<button type="button" class="btn-close" aria-label="Fermer" onclick={() => (menuOpen = false)}></button>
			</div>
			<div class="offcanvas-body justify-content-lg-center w-full py-0">
				<div class="navbar-nav my-2 flex-column flex-lg-row gap-lg-4" data-test-menu>
					<span class="nav-link yack-nav-static" title="Contenu éditorial hors périmètre du POC">Le groupe</span>
					<span class="nav-link yack-nav-static" title="Contenu éditorial hors périmètre du POC">Les marques</span>
					<div class="nav-item dropdown">
						<button class="nav-link dropdown-toggle btn btn-link" aria-expanded={open === 'services'} onclick={() => toggle('services')}>Nos services</button>
						<div class="dropdown-menu" class:show={open === 'services'}>
							{#if servicesPage}<a class="dropdown-item" href="/nos-services">Tous nos services</a>{/if}
							<a class="dropdown-item" href="/sav">SAV &amp; pièces détachées</a>
							<a class="dropdown-item" href="/trouver-un-installateur">Trouver un installateur</a>
						</div>
					</div>
					<div class="nav-item dropdown" data-test-menu-item="Nos produits">
						<button class="nav-link d-flex align-items-center gap-1 btn btn-link" aria-expanded={open === 'produits'} onclick={() => toggle('produits')}>
							Nos produits
							<Icon name="chevron" size="sm" />
						</button>
						<div class="dropdown-menu" class:show={open === 'produits'}>
							<a class="nav-link" href="/produits" title="Clik&amp;Yack">Clik&amp;Yack</a>
							<a class="nav-link" href="/pieces" title="Clik&amp;Yack Parts">Clik&amp;Yack Parts</a>
						</div>
					</div>
					<span class="nav-link yack-nav-static" title="Contenu éditorial hors périmètre du POC">Nos documentations</span>
					<a class="nav-link ms-lg-auto fw-semibold" href="/compte/commandes"><Icon name="truck" /> Suivez vos commandes</a>
				</div>
			</div>
		</nav>
		{#if menuOpen}
			<div class="offcanvas-backdrop fade show d-lg-none" role="presentation" onclick={() => (menuOpen = false)}></div>
		{/if}
	</div>
</header>
