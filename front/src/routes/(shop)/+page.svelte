<script lang="ts">
	import CmsPageView from '#lib/blocks/CmsPageView.svelte';
	import LatestProducts from '#lib/catalog/LatestProducts.svelte';

	let { data } = $props();
</script>

<svelte:head>
	<title>{data.cms?.page.metaTitle || 'Clik&Yack — la boutique des professionnels du génie climatique'}</title>
	{#if data.cms?.page.metaDescription}<meta name="description" content={data.cms.page.metaDescription} />{/if}
	{#if data.cms?.preview}<meta name="robots" content="noindex" />{/if}
</svelte:head>

{#if data.cms}
	<CmsPageView page={data.cms.page} preview={data.cms.preview} catalog={data.cms.catalog} viewer={data.viewer} />
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
{/if}

<LatestProducts products={data.latest} viewer={data.viewer} />
