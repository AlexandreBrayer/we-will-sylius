<script lang="ts" module>
	/** Vertical menu of the listing side column: catalog sections or sub-categories. */
	export interface ListingMenu {
		items: { name: string; url: string }[];
		/** Parent level URL (null at the top). */
		up: string | null;
	}
</script>

<script lang="ts">
	// Port of the Sylius product index body, at the Yack charter (demo « Demain »): side column
	// (catalog sections menu, then the facets of category pages), toolbar (count, search within the
	// listing, sort as a pill), product grid and pill pagination. Everything is a link, a GET form on
	// the current URL or a <details>: works without JavaScript. Used by the search page and the
	// "categorie-produits" block; there, `suggestIn` turns "Rechercher dans la liste" into the field with
	// suggestions of the header (SearchSuggest) limited to the category, submitting the same GET form. The page size is not chosen here: it comes from the block (template
	// of the category) or from the listing defaults, and no generated link carries ?par=.
	import { page } from '$app/state';
	import Icon from '#lib/layout/Icon.svelte';
	import SearchSuggest from '#lib/layout/SearchSuggest.svelte';
	import {
		FILTER_PARAMS,
		PRICE_PARAMS,
		type FacetCode,
		type ListingParams,
		type ProductPage,
		type SortKey,
		type SortOption,
		type Viewer
	} from '#lib/types.js';
	import Facets from './Facets.svelte';
	import { listingUrl, type UrlChange } from './listingUrl.js';
	import { pageWindow } from './pagination.js';
	import ProductGrid from './ProductGrid.svelte';

	let {
		result,
		params,
		sorts,
		viewer,
		defaults = { sort: 'position' },
		menu = null,
		empty = '',
		suggestIn = null
	}: {
		result: ProductPage;
		params: ListingParams;
		sorts: SortOption[];
		viewer: Viewer;
		/** Initial sort: left out of the URLs. */
		defaults?: { sort: SortKey };
		/** Vertical menu of the side column (catalog sections, sub-categories). */
		menu?: ListingMenu | null;
		/** Message without product and without search (default: empty category). */
		empty?: string;
		/** Category code: suggestions while typing in "Rechercher dans la liste", limited to it. */
		suggestIn?: string | null;
	} = $props();

	let sortOpen = $state(false);

	// Another listing state (link followed): the sort menu closes.
	$effect(() => {
		void page.url.search;
		sortOpen = false;
	});

	/** Current URL with some parameters changed (initial values dropped, back to page 1 unless set; see listingUrl). */
	function href(changes: Record<string, UrlChange>): string {
		return listingUrl(page.url, changes, { tri: defaults.sort, page: '1' });
	}

	const sortLabel = $derived(sorts.find((s) => s.key === params.sort)?.label ?? sorts[0].label);
	const pages = $derived(pageWindow(result.page, result.pages));
	// Page past the last one (old link made with another page size, ?page= typed by hand): no product
	// on it, but the listing has some; the previous / last page links lead back to them.
	const beyond = $derived(result.total > 0 && result.page > result.pages);
	const hasMenu = $derived(menu !== null && (menu.items.length > 0 || menu.up !== null));
</script>

<svelte:window onclick={(event) => sortOpen && !(event.target as HTMLElement).closest('.yack-listing__sort') && (sortOpen = false)} />

<div class="row yack-listing">
	<aside class="col-12 col-lg-3 col-xl-2 yack-listing__aside" aria-label="Catégories et filtres">
		{#if menu && hasMenu}
			<nav class="yack-listing__panel" aria-label="Catégories" data-test-vertical-menu>
				<h2 class="yack-listing__panel-title">Catégories</h2>
				<ul class="yack-listing__menu">
					{#each menu.items as item (item.url)}
						<li><a href={item.url} data-test-vertical-menu-item>{item.name}</a></li>
					{/each}
				</ul>
				{#if menu.up}
					<a href={menu.up} class="yack-listing__up" data-test-vertical-menu-go-level-up><Icon name="chevron" />Monter au niveau supérieur</a>
				{/if}
			</nav>
		{/if}
		{#if result.facets}
			<Facets facets={result.facets} filters={params.filters} price={params.price} link={href} />
		{/if}
	</aside>

	<div class="col-12 col-lg-9 col-xl-10 yack-listing__main">
		<div class="yack-listing__toolbar">
			{#if result.total > 0}
				<p class="yack-listing__count" data-test-listing-count><b>{result.total}</b> produit{result.total > 1 ? 's' : ''}</p>
			{/if}
			<form class="yack-listing__search" method="get" action={page.url.pathname} role="search">
				{#if params.sort !== defaults.sort}<input type="hidden" name="tri" value={params.sort} />{/if}
				{#each Object.entries(params.filters) as [code, values] (code)}
					{#each values as value (value)}
						<input type="hidden" name={FILTER_PARAMS[code as FacetCode]} {value} />
					{/each}
				{/each}
				{#if params.price.min !== null}<input type="hidden" name={PRICE_PARAMS.min} value={params.price.min} />{/if}
				{#if params.price.max !== null}<input type="hidden" name={PRICE_PARAMS.max} value={params.price.max} />{/if}
				{#if suggestIn}
					<div class="yack-listing__field">
						<SearchSuggest
							value={params.q}
							category={suggestIn}
							allHref={(text) => href({ q: text })}
							label="Rechercher dans la liste"
							placeholder="Rechercher dans la liste"
							size="sm"
						/>
					</div>
				{:else}
					<input type="search" name="q" value={params.q} autocomplete="off" placeholder="Rechercher dans la liste" aria-label="Rechercher dans la liste" class="form-control form-control-sm" />
				{/if}
				<button class="btn btn-primary btn-sm" type="submit" aria-label="Rechercher"><Icon name="search" /></button>
				{#if params.q}
					<a class="btn btn-light btn-sm" href={href({ q: '' })} aria-label="Effacer la recherche"><Icon name="close" /></a>
				{/if}
			</form>
			<div class="yack-listing__controls">
				<details class="yack-listing__sort" bind:open={sortOpen}>
					<summary class="yack-listing__pill"><span class="yack-listing__label">Trier :</span>{sortLabel}<Icon name="chevron" /></summary>
					<ul class="yack-listing__options">
						{#each sorts as sort (sort.key)}
							<li><a href={href({ tri: sort.key })} aria-current={sort.key === params.sort ? 'true' : undefined}>{sort.label}</a></li>
						{/each}
					</ul>
				</details>
			</div>
		</div>

		<!-- Another sort / page / search of the same listing: the current products stay, dimmed, until the new ones arrive. -->
		<div aria-busy={$effect.pending() > 0} class:opacity-50={$effect.pending() > 0}>
			{#if result.items.length > 0}
				<ProductGrid products={result.items} {viewer} />
			{:else if beyond}
				<p class="yack-listing__empty" role="status">
					Cette page n'existe pas : la liste compte {result.pages} page{result.pages > 1 ? 's' : ''}.
					<a href={href({ page: result.pages })} data-test-listing-last-page>Voir la page {result.pages}</a>
				</p>
			{:else}
				<p class="yack-listing__empty" role="status">
					{params.q
						? `Aucun produit ne correspond à « ${params.q} ».`
						: Object.keys(params.filters).length > 0
							? 'Aucun produit ne correspond à ces filtres.'
							: empty || 'Aucun produit dans cette catégorie.'}
				</p>
			{/if}
		</div>

		{#if result.pages > 1}
			<nav aria-label="Pagination">
				<ul class="pagination yack-pagination justify-content-center mt-5">
					<li class="page-item" class:disabled={result.page === 1}>
						{#if result.page === 1}<span class="page-link" aria-hidden="true">‹</span>
						{:else}<a class="page-link" href={href({ page: Math.min(result.page - 1, result.pages) })} rel="prev" aria-label="Page précédente">‹</a>{/if}
					</li>
					{#each pages as n, i (n === 'gap' ? `gap-${i}` : n)}
						{#if n === 'gap'}
							<li class="page-item disabled yack-pagination__gap" aria-hidden="true"><span class="page-link">…</span></li>
						{:else}
							<li class="page-item" class:active={n === result.page}>
								{#if n === result.page}<span class="page-link" aria-current="page">{n}</span>
								{:else}<a class="page-link" href={href({ page: n })}>{n}</a>{/if}
							</li>
						{/if}
					{/each}
					<li class="page-item" class:disabled={result.page >= result.pages}>
						{#if result.page >= result.pages}<span class="page-link" aria-hidden="true">›</span>
						{:else}<a class="page-link" href={href({ page: result.page + 1 })} rel="next" aria-label="Page suivante">›</a>{/if}
					</li>
				</ul>
			</nav>
		{/if}
	</div>
</div>
