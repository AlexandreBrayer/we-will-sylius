<script lang="ts">
	// Copie de l'en-tête de la boutique (templates/shop/yack/layout/*) : mêmes classes, liens vers Sylius.
	let { syliusUrl }: { syliusUrl: string } = $props();
	const shop = (path: string) => `${syliusUrl}/fr_FR${path}`;
	let open = $state<string | null>(null);
	const toggle = (menu: string) => (open = open === menu ? null : menu);
</script>

<svelte:window onclick={(event) => !(event.target as HTMLElement).closest('.dropdown') && (open = null)} />

<header>
	<div class="bg-black">
		<div class="container py-2 fs-7">
			<div class="row g-3 align-items-center">
				<div class="col-12 col-md">
					<a href={shop('/')}>Clik&amp;Yack — la boutique des professionnels du génie climatique (POC Sylius)</a>
				</div>
			</div>
		</div>
	</div>
	<div class="border-bottom py-4">
		<div class="container">
			<div class="row align-items-center">
				<div class="col-auto">
					<a href={shop('/')} class="d-inline-block" aria-label="Yack — accueil">
						<img src="{syliusUrl}/yack/logo-yack.png" alt="Yack — Clik&amp;Yack" class="yack-logo" width="1146" height="1031" />
					</a>
				</div>
				<div class="col-12 col-lg order-last order-lg-0 mt-3 mt-lg-0">
					<form class="yack-search input-group" method="get" action={shop('/taxons/nos-produits')} role="search">
						<input class="form-control" type="search" name="criteria[search][value]" placeholder="Rechercher un produit, une référence…" aria-label="Rechercher" />
						<button class="btn btn-primary" type="submit">Rechercher</button>
					</form>
				</div>
				<div class="col-auto">
					<div class="d-flex align-items-center gap-2 ps-2">
						<a href={shop('/login')} class="link-reset">Connexion</a>
						<small aria-hidden="true" class="text-black px-1">|</small>
						<a href={shop('/inscription')} class="link-reset">Créer un compte</a>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="w-100 border-bottom">
		<nav class="navbar p-0" aria-label="Navigation principale">
			<div class="container justify-content-lg-center py-0">
				<div class="navbar-nav my-2 flex-column flex-lg-row gap-lg-4 w-100">
					<span class="nav-link yack-nav-static">Le groupe</span>
					<span class="nav-link yack-nav-static">Les marques</span>
					<div class="nav-item dropdown">
						<button class="nav-link dropdown-toggle btn btn-link" aria-expanded={open === 'services'} onclick={() => toggle('services')}>Nos services</button>
						<div class="dropdown-menu" class:show={open === 'services'}>
							<a class="dropdown-item" href="/nos-services">Tous nos services</a>
							<a class="dropdown-item" href={shop('/sav')}>SAV &amp; pièces détachées</a>
							<a class="dropdown-item" href={shop('/trouver-un-installateur')}>Trouver un installateur</a>
						</div>
					</div>
					<div class="nav-item dropdown">
						<button class="nav-link dropdown-toggle btn btn-link" aria-expanded={open === 'produits'} onclick={() => toggle('produits')}>Nos produits</button>
						<div class="dropdown-menu" class:show={open === 'produits'}>
							<a class="dropdown-item" href={shop('/taxons/nos-produits/clik-yack')}>Clik&amp;Yack</a>
							<a class="dropdown-item" href={shop('/taxons/nos-produits/clik-yack-parts')}>Clik&amp;Yack Parts</a>
						</div>
					</div>
					<span class="nav-link yack-nav-static">Nos documentations</span>
					<a class="nav-link ms-lg-auto fw-semibold" href={shop('/account/orders/')}>Suivez vos commandes</a>
				</div>
			</div>
		</nav>
	</div>
</header>
