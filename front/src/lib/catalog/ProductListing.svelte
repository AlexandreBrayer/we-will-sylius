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
	// listing, page size and sort as pills), product grid and pill pagination. Everything is a link,
	// a GET form on the current URL or a <details>: works without JavaScript. Used by the search
	// page and the "categorie-produits" block.
	import { page } from '$app/state';
	import Icon from '#lib/layout/Icon.svelte';
	import { FILTER_PARAMS, type FacetCode, type ListingParams, type ProductPage, type SortKey, type SortOption, type Viewer } from '#lib/types.js';
	import Facets from './Facets.svelte';
	import ProductGrid from './ProductGrid.svelte';

	let {
		result,
		params,
		sorts,
		viewer,
		defaults = { sort: 'position', perPage: 9 },
		menu = null,
		empty = ''
	}: {
		result: ProductPage;
		params: ListingParams;
		sorts: SortOption[];
		viewer: Viewer;
		/** Initial sort / page size: left out of the URLs. */
		defaults?: { sort: SortKey; perPage: number };
		/** Vertical menu of the side column (catalog sections, sub-categories). */
		menu?: ListingMenu | null;
		/** Message without product and without search (default: empty category). */
		empty?: string;
	} = $props();

	const PER_PAGE = [9, 18, 27];

	let sortOpen = $state(false);

	// Another listing state (link followed): the sort menu closes.
	$effect(() => {
		void page.url.search;
		sortOpen = false;
	});

	/** Current URL with some parameters changed (initial values dropped, back to page 1 unless set). */
	function href(changes: Record<string, string | number>): string {
		const initial: Record<string, string> = { par: String(defaults.perPage), tri: defaults.sort, page: '1' };
		const query = new URLSearchParams(page.url.search);
		query.delete('page');
		for (const [name, value] of Object.entries(changes)) {
			if (String(value) === initial[name] || value === '') query.delete(name);
			else query.set(name, String(value));
		}
		const search = query.toString();
		return search ? `${page.url.pathname}?${search}` : page.url.pathname;
	}

	const sortLabel = $derived(sorts.find((s) => s.key === params.sort)?.label ?? sorts[0].label);
	const pages = $derived(Array.from({ length: result.pages }, (_, i) => i + 1));
	const hasMenu = $derived(menu !== null && (menu.items.length > 0 || menu.up !== null));
</script>

<svelte:window onclick={(event) => sortOpen && !(event.target as HTMLElement).closest('.yack-listing__sort') && (sortOpen = false)} />

<div class="row yack-listing">
	<aside class="col-12 col-lg-3 yack-listing__aside" aria-label="Catégories et filtres">
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
			<Facets facets={result.facets} filters={params.filters} link={href} />
		{/if}
	</aside>

	<div class="col-12 col-lg-9 yack-listing__main">
		<div class="yack-listing__toolbar">
			{#if result.total > 0}
				<p class="yack-listing__count" data-test-listing-count><b>{result.total}</b> produit{result.total > 1 ? 's' : ''}</p>
			{/if}
			<form class="yack-listing__search" method="get" action={page.url.pathname} role="search">
				{#if params.perPage !== defaults.perPage}<input type="hidden" name="par" value={params.perPage} />{/if}
				{#if params.sort !== defaults.sort}<input type="hidden" name="tri" value={params.sort} />{/if}
				{#each Object.entries(params.filters) as [code, value] (code)}
					<input type="hidden" name={FILTER_PARAMS[code as FacetCode]} {value} />
				{/each}
				<input type="search" name="q" value={params.q} placeholder="Rechercher dans la liste" aria-label="Rechercher dans la liste" class="form-control form-control-sm" />
				<button class="btn btn-primary btn-sm" type="submit" aria-label="Rechercher"><Icon name="search" /></button>
				{#if params.q}
					<a class="btn btn-light btn-sm" href={href({ q: '' })} aria-label="Effacer la recherche"><Icon name="close" /></a>
				{/if}
			</form>
			<div class="yack-listing__controls">
				<div class="yack-listing__group" role="group" aria-label="Produits par page">
					<span class="yack-listing__label" aria-hidden="true">Afficher</span>
					{#each PER_PAGE as n (n)}
						<a class="yack-listing__pill" href={href({ par: n })} aria-current={n === params.perPage ? 'true' : undefined} aria-label="{n} produits par page">{n}</a>
					{/each}
				</div>
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
						{:else}<a class="page-link" href={href({ page: result.page - 1 })} rel="prev" aria-label="Page précédente">‹</a>{/if}
					</li>
					{#each pages as n (n)}
						<li class="page-item" class:active={n === result.page}>
							{#if n === result.page}<span class="page-link" aria-current="page">{n}</span>
							{:else}<a class="page-link" href={href({ page: n })}>{n}</a>{/if}
						</li>
					{/each}
					<li class="page-item" class:disabled={result.page === result.pages}>
						{#if result.page === result.pages}<span class="page-link" aria-hidden="true">›</span>
						{:else}<a class="page-link" href={href({ page: result.page + 1 })} rel="next" aria-label="Page suivante">›</a>{/if}
					</li>
				</ul>
			</nav>
		{/if}
	</div>
</div>
