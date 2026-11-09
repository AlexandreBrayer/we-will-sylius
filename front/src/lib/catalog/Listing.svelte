<script lang="ts">
	// Search page (/recherche) as the Sylius product index: breadcrumb, heading aligned on the product
	// column, then the listing body (ProductListing: catalog sections menu, toolbar, grid, pagination).
	// Data: ListingData of getSearchListing (#lib/remote/catalog.remote.ts), spread by the page.
	// Category pages are rendered through their CMS template (#lib/blocks/TemplateView.svelte).
	import { getViewer } from '#lib/remote/session.remote.js';
	import type { Breadcrumb, ListingParams, ProductPage, SortOption } from '#lib/types.js';
	import Breadcrumbs from './Breadcrumbs.svelte';
	import ProductListing, { type ListingMenu } from './ProductListing.svelte';

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
		menu?: ListingMenu | null;
		result: ProductPage;
		params: ListingParams;
		sorts: SortOption[];
	} = $props();

	const viewer = $derived(await getViewer());
</script>

<div class="container mt-4 mb-5">
	<Breadcrumbs items={breadcrumb} />
	<div class="row mt-4">
		<div class="col-12 col-lg-9 offset-lg-3">
			<h1 class="mb-4 text-break">{heading}</h1>
		</div>
	</div>
	<ProductListing {result} {params} {sorts} {viewer} {menu} empty="Saisissez le nom ou la référence d'un produit dans la recherche." />
</div>
