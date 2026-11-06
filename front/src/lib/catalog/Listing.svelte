<script lang="ts">
	// Port of the Sylius product index (taxon page): breadcrumb, vertical taxon menu, search
	// within the listing, "Afficher" / "Trier" dropdowns, product grid and pagination.
	// Everything is a link or a GET form: works without JavaScript. Data: ListingData of the
	// listing queries (#lib/remote/catalog.remote.ts), spread by the page.
	import { page } from '$app/state';
	import Icon from '#lib/layout/Icon.svelte';
	import { getViewer } from '#lib/remote/session.remote.js';
	import type { Breadcrumb, ListingParams, ProductPage, SortOption } from '#lib/types.js';
	import Breadcrumbs from './Breadcrumbs.svelte';
	import ProductGrid from './ProductGrid.svelte';

	let {
		heading,
		description = null,
		breadcrumb,
		menu = null,
		result,
		params,
		sorts
	}: {
		heading: string;
		description?: string | null;
		breadcrumb: Breadcrumb[];
		/** Vertical menu: sub-taxons and the parent level (null at the top of a section). */
		menu?: { items: { name: string; url: string }[]; up: string | null } | null;
		result: ProductPage;
		params: ListingParams;
		sorts: SortOption[];
	} = $props();

	const viewer = $derived(await getViewer());

	const PER_PAGE = [9, 18, 27];
	const DEFAULTS: Record<string, string> = { par: '9', tri: 'position', page: '1' };

	let open = $state<string | null>(null);
	const toggle = (menu: string) => (open = open === menu ? null : menu);

	$effect(() => {
		void page.url.search;
		open = null;
	});

	/** Current URL with some parameters changed (defaults dropped, back to page 1 unless set). */
	function href(changes: Record<string, string | number>): string {
		const query = new URLSearchParams(page.url.search);
		query.delete('page');
		for (const [name, value] of Object.entries(changes)) {
			if (String(value) === DEFAULTS[name] || value === '') query.delete(name);
			else query.set(name, String(value));
		}
		const search = query.toString();
		return search ? `${page.url.pathname}?${search}` : page.url.pathname;
	}

	const sortLabel = $derived(sorts.find((s) => s.key === params.sort)?.label ?? sorts[0].label);
	const pages = $derived(Array.from({ length: result.pages }, (_, i) => i + 1));
</script>

<svelte:window onclick={(event) => !(event.target as HTMLElement).closest('.dropdown') && (open = null)} />

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
				{#if description}<div>{description}</div>{/if}
			</div>
			<div class="row flex-column flex-md-row align-items-end justify-content-end gap-2 gap-md-0 mb-5">
				<div class="col">
					<form method="get" action={page.url.pathname} role="search">
						{#if params.perPage !== 9}<input type="hidden" name="par" value={params.perPage} />{/if}
						{#if params.sort !== 'position'}<input type="hidden" name="tri" value={params.sort} />{/if}
						<div class="input-group">
							<input type="text" name="q" value={params.q} placeholder="Rechercher dans la liste" aria-label="Rechercher dans la liste" class="form-control-sm form-control" />
							<button class="btn btn-outline-secondary btn-sm" type="submit" aria-label="Rechercher"><Icon name="search" /></button>
							<a class="btn btn-outline-secondary btn-sm" href={href({ q: '' })} aria-label="Effacer la recherche"><Icon name="close" /></a>
						</div>
					</form>
				</div>
				<div class="col-auto d-flex gap-2">
					<div class="dropdown">
						<button type="button" class="btn btn-sm dropdown-toggle" aria-expanded={open === 'par'} onclick={() => toggle('par')}>
							<span class="text-black-50">Afficher:</span> {params.perPage}
						</button>
						<div class="dropdown-menu dropdown-menu-end" class:show={open === 'par'}>
							{#each PER_PAGE.filter((n) => n !== params.perPage) as n (n)}
								<a class="dropdown-item" href={href({ par: n })}>{n}</a>
							{/each}
						</div>
					</div>
					<div class="dropdown">
						<button type="button" class="btn btn-sm dropdown-toggle" aria-expanded={open === 'tri'} onclick={() => toggle('tri')}>
							<span class="text-black-50">Trier:</span> {sortLabel.toLowerCase()}
						</button>
						<div class="dropdown-menu dropdown-menu-end" class:show={open === 'tri'}>
							{#each sorts as sort (sort.key)}
								<a class="dropdown-item" class:active={sort.key === params.sort} href={href({ tri: sort.key })}>{sort.label}</a>
							{/each}
						</div>
					</div>
				</div>
			</div>

			<!-- Another sort / page / search of the same listing: the current products stay, dimmed, until the new ones arrive. -->
			<div aria-busy={$effect.pending() > 0} class:opacity-50={$effect.pending() > 0}>
				{#if result.items.length > 0}
					<ProductGrid products={result.items} {viewer} />
				{:else}
					<div class="alert alert-info" role="status">
						{params.q ? `Aucun produit ne correspond à « ${params.q} ».` : 'Aucun produit dans cette catégorie.'}
					</div>
				{/if}
			</div>

			{#if result.pages > 1}
				<ul class="pagination justify-content-center mt-4">
					<li class="page-item" class:disabled={result.page === 1}>
						{#if result.page === 1}<span class="page-link">Précédent</span>
						{:else}<a class="page-link" href={href({ page: result.page - 1 })} rel="prev">Précédent</a>{/if}
					</li>
					{#each pages as n (n)}
						<li class="page-item" class:active={n === result.page} aria-current={n === result.page ? 'page' : undefined}>
							{#if n === result.page}<span class="page-link">{n}</span>
							{:else}<a class="page-link" href={href({ page: n })}>{n}</a>{/if}
						</li>
					{/each}
					<li class="page-item" class:disabled={result.page === result.pages}>
						{#if result.page === result.pages}<span class="page-link">Suivant</span>
						{:else}<a class="page-link" href={href({ page: result.page + 1 })} rel="next">Suivant</a>{/if}
					</li>
				</ul>
			{/if}
		</div>
	</div>
</div>
