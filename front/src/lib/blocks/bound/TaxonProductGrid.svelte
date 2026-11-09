<script lang="ts">
	// categorie-produits (mandatory block of a category template): side column (sub-categories and
	// parent level unless `menu` is `non`, then the facets) and the product listing: search, sort,
	// pagination from the URL (?q=&tri=&page=) over the block's initial sort; the page size is the
	// block's. SSR: listing loaded with the page (TaxonContext); builder canvas: loaded for the
	// block's props through `loaders.listing`.
	import ProductListing from '#lib/catalog/ProductListing.svelte';
	import type { SortKey } from '#lib/types.js';
	import { getYackContext } from '../context.js';
	import type { TaxonListing } from '../types.js';
	import EditorNote from './EditorNote.svelte';

	let { perPage = '9', sort = 'position', menu = 'oui' }: { perPage?: string; sort?: string; menu?: string } = $props();
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
		{#if listing}
			<ProductListing
				{...listing}
				viewer={ctx.viewer}
				menu={menu === 'non' ? null : { items: taxon.children, up: taxon.up }}
			/>
		{/if}
	</div>
{:else}
	<EditorNote label="Liste des produits" text="Choisissez une catégorie d'exemple pour l'aperçu." />
{/if}
