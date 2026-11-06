<script lang="ts">
	// Search page (/recherche) as the Sylius product index: breadcrumb, vertical menu (catalog
	// sections), heading and the listing body (ProductListing). Data: ListingData of
	// getSearchListing (#lib/remote/catalog.remote.ts), spread by the page. Category pages are
	// rendered through their CMS template (#lib/blocks/TemplateView.svelte).
	import { getViewer } from '#lib/remote/session.remote.js';
	import type { Breadcrumb, ListingParams, ProductPage, SortOption } from '#lib/types.js';
	import Breadcrumbs from './Breadcrumbs.svelte';
	import ProductListing from './ProductListing.svelte';

	let {
		heading,
		breadcrumb,
		menu = null,
		result,
		params,
		sorts
	}: {
		heading: string;
		breadcrumb: Breadcrumb[];
		/** Vertical menu: catalog sections. */
		menu?: { items: { name: string; url: string }[]; up: string | null } | null;
		result: ProductPage;
		params: ListingParams;
		sorts: SortOption[];
	} = $props();

	const viewer = $derived(await getViewer());
</script>

<div class="container mt-4 mb-5">
	<Breadcrumbs items={breadcrumb} />
	<div class="row mt-5">
		<div class="col-12 col-lg-3">
			{#if menu}
				<div data-test-vertical-menu>
					<div class="d-flex flex-column">
						{#each menu.items as item (item.url)}
							<a href={item.url} class="d-inline-block py-1 link-reset" data-test-vertical-menu-item>{item.name}</a>
						{/each}
						{#if menu.up}
							<a href={menu.up} class="d-inline-block py-1 link-reset" data-test-vertical-menu-go-level-up>Monter au niveau supérieur</a>
						{/if}
					</div>
				</div>
			{/if}
		</div>
		<div class="col-12 col-lg-9">
			<div class="mb-4">
				<h1 class="mb-3 text-break">{heading}</h1>
			</div>
			<ProductListing {result} {params} {sorts} {viewer} />
		</div>
	</div>
</div>
