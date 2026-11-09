<script lang="ts">
	// Homepage = CMS page "accueil"; while it is not published, static hero + "Derniers produits"
	// (the CMS homepage of the maquette has its own "Produits à la une" block).
	import { page } from '$app/state';
	import CmsPageView from '#lib/blocks/CmsPageView.svelte';
	import LatestProducts from '#lib/catalog/LatestProducts.svelte';
	import { getHomePage } from '#lib/remote/cms.remote.js';
	import { getViewer } from '#lib/remote/session.remote.js';

	const cms = $derived(await getHomePage(page.url.searchParams.has('apercu')));
</script>

<svelte:head>
	<title>{cms?.page.metaTitle || 'Clik&Yack — la boutique des professionnels du génie climatique'}</title>
	{#if cms?.page.metaDescription}<meta name="description" content={cms.page.metaDescription} />{/if}
	{#if cms?.preview}<meta name="robots" content="noindex" />{/if}
</svelte:head>

{#if cms}
	<CmsPageView page={cms.page} preview={cms.preview} catalog={cms.catalog} viewer={await getViewer()} />
{:else}
	<!-- No published "accueil" page: static hero of the shop (templates/shop/yack/homepage/hero.html.twig). -->
	<div class="yack-hero mb-6" role="banner" aria-label="Accueil">
		<div class="container py-6">
			<div class="row align-items-center g-5">
				<div class="col-lg-7">
					<h1 class="display-5 fw-bold">Le génie climatique, en un clic.</h1>
					<p class="lead mb-4">Pompes à chaleur, climatisation, eau chaude sanitaire et pièces détachées d'origine — pour les installateurs et les particuliers.</p>
					<div class="d-flex flex-wrap gap-2">
						<a class="btn btn-light btn-lg" href="/produits">Clik&amp;Yack</a>
						<a class="btn btn-outline-light btn-lg" href="/pieces">Clik&amp;Yack Parts</a>
					</div>
				</div>
				<div class="col-lg-5">
					<div class="row g-3">
						<div class="col-12">
							<a class="yack-tile" href="/sav">
								<span class="fw-bold">SAV : trouver une pièce</span>
								<span class="small">À partir du numéro de série de l'appareil</span>
							</a>
						</div>
						<div class="col-12">
							<a class="yack-tile" href="/trouver-un-installateur">
								<span class="fw-bold">Trouver un installateur</span>
								<span class="small">Le réseau de partenaires Yack près de chez vous</span>
							</a>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<LatestProducts />
{/if}
