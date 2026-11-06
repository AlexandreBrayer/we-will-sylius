<script lang="ts">
	// Copie du pied de page boutique (templates/shop/yack/layout/footer_menu.html.twig, footer_copy.html.twig).
	import type { CmsPageSummary } from '$lib/blocks/types.js';

	let { syliusUrl, pages }: { syliusUrl: string; pages: CmsPageSummary[] } = $props();
	const shop = (path: string) => `${syliusUrl}/fr_FR${path}`;
</script>

<div class="mt-auto border-top mt-5 py-5">
	<footer class="container">
		<div class="row">
			<div class="col-12 col-lg-6 order-lg-2">
				<div class="row">
					<div class="col-12 col-sm-6">
						<nav aria-label="Pages Clik&amp;Yack" class="nav flex-column">
							<span class="nav-link fw-bold text-body">Clik&amp;Yack</span>
							{#each pages as page (page.slug)}
								<a class="nav-link" href="/{page.slug}">{page.title}</a>
							{/each}
							<a class="nav-link" href={shop('/contact/')}>Nous contacter</a>
						</nav>
					</div>
					<div class="col-12 col-sm-6">
						<nav aria-label="Services" class="nav flex-column mb-5">
							<span class="nav-link fw-bold text-body">Services</span>
							<a class="nav-link" href={shop('/sav')}>SAV &amp; pièces détachées</a>
							<a class="nav-link" href={shop('/trouver-un-installateur')}>Trouver un installateur</a>
							<a class="nav-link" href={shop('/account/orders/')}>Suivre mes commandes</a>
						</nav>
					</div>
				</div>
			</div>
			<div class="col-12 text-center col-md-6 order-md-3 text-md-start col-lg-3 order-lg-1">
				<a href={shop('/')} class="d-inline-block mb-4" aria-label="Yack — accueil">
					<img src="{syliusUrl}/yack/logo-yack.png" alt="Yack — Clik&amp;Yack" class="yack-logo" width="1146" height="1031" />
				</a>
				<div class="mb-3 text-body-secondary">
					&copy; Yack — Clik&amp;Yack (POC), propulsé par
					<a href="https://sylius.com" target="_blank" rel="noopener" class="text-decoration-underline">Sylius</a>
				</div>
			</div>
		</div>
	</footer>
</div>
