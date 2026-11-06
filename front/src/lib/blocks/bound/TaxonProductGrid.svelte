<script lang="ts">
	// categorie-produits (mandatory block of a category template): vertical menu (sub-categories,
	// parent level) and the product listing — search, page size, sort, pagination from the URL
	// (?q=&par=&tri=&page=) over the block's initial sort / page size. SSR: listing loaded with the
	// page (TaxonContext); builder canvas: loaded for the block's props through `loaders.listing`.
	import ProductListing from '#lib/catalog/ProductListing.svelte';
	import type { SortKey } from '#lib/types.js';
	import { getYackContext } from '../context.js';
	import type { TaxonListing } from '../types.js';
	import EditorNote from './EditorNote.svelte';

	let { perPage = '9', sort = 'position' }: { perPage?: string; sort?: string } = $props();
	const ctx = getYackContext();
	const taxon = $derived(ctx.taxon ?? null);

	let previewed = $state<TaxonListing | null>(null);
	$effect(() => {
		if (!ctx.editor || !ctx.loaders || !taxon) return;
		let cancelled = false;
		ctx.loaders
			.listing({ taxonCode: taxon.taxon.code, sort: sort as SortKey, perPage: Number(perPage) || 9 })
			.then((listing) => {
				if (!cancelled) previewed = listing;
			})
			.catch(() => undefined);
		return () => {
			cancelled = true;
		};
	});

	const listing = $derived(ctx.editor ? previewed : (taxon?.listing ?? null));
</script>

{#if taxon}
	<div class="container mb-5" data-yack-block="categorie-produits">
		<div class="row">
			<div class="col-12 col-lg-3">
				<div data-test-vertical-menu>
					<div class="d-flex flex-column">
						{#each taxon.children as item (item.url)}
							<a href={item.url} class="d-inline-block py-1 link-reset" data-test-vertical-menu-item>{item.name}</a>
						{/each}
						{#if taxon.up}
							<a href={taxon.up} class="d-inline-block py-1 link-reset" data-test-vertical-menu-go-level-up>Monter au niveau supérieur</a>
						{/if}
					</div>
				</div>
			</div>
			<div class="col-12 col-lg-9">
				{#if listing}
					<ProductListing {...listing} viewer={ctx.viewer} />
				{/if}
			</div>
		</div>
	</div>
{:else}
	<EditorNote label="Liste des produits" text="Choisissez une catégorie d'exemple pour l'aperçu." />
{/if}
