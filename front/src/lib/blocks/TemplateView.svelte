<script lang="ts">
	// Product page / category page rendered through its CMS template: breadcrumb, then the blocks
	// in order. Consecutive product blocks share the two-column layout of the Sylius product page
	// (main column: gallery, description, attributes, documents; sticky side box: infos, purchase,
	// SAV link), at the proportions of the demo product page; free blocks and the other bound blocks
	// span the page.
	import { page as appPage } from '$app/state';
	import Breadcrumbs from '#lib/catalog/Breadcrumbs.svelte';
	import type { Breadcrumb, Viewer } from '#lib/types.js';
	import BlockRenderer from './BlockRenderer.svelte';
	import { setYackContext } from './context.js';
	import type { BlockType, Catalog, PageBlock, ProductContext, TaxonContext } from './types.js';

	let {
		blocks,
		product = null,
		taxon = null,
		catalog,
		viewer,
		preview = null
	}: {
		blocks: PageBlock[];
		product?: ProductContext | null;
		taxon?: TaxonContext | null;
		catalog: Catalog;
		viewer: Viewer;
		/** Template previewed by an administrator from the builder (?apercu=<id>). */
		preview?: { name: string; status: string } | null;
	} = $props();

	// Getters: the context follows the props when navigating from a product / category to another.
	setYackContext({
		get viewer() {
			return viewer;
		},
		get catalog() {
			return catalog;
		},
		get product() {
			return product;
		},
		get taxon() {
			return taxon;
		},
		editor: false,
		// Login and sign-up links of the blocks come back here (links.ts).
		get returnPath() {
			return appPage.url.pathname + appPage.url.search;
		}
	});

	const SIDE: BlockType[] = ['produit-infos', 'produit-achat', 'produit-sav'];
	const MAIN: BlockType[] = ['produit-galerie', 'produit-description', 'produit-caracteristiques', 'produit-documents'];

	type Section = { kind: 'block'; block: PageBlock } | { kind: 'product'; side: PageBlock[]; main: PageBlock[] };

	const sections = $derived.by(() => {
		const result: Section[] = [];
		for (const block of blocks) {
			const side = SIDE.includes(block.type);
			if (!side && !MAIN.includes(block.type)) {
				result.push({ kind: 'block', block });
				continue;
			}
			let last = result.at(-1);
			if (last?.kind !== 'product') {
				last = { kind: 'product', side: [], main: [] };
				result.push(last);
			}
			(side ? last.side : last.main).push(block);
		}
		return result;
	});

	const breadcrumb: Breadcrumb[] = $derived(product?.breadcrumb ?? taxon?.breadcrumb ?? []);
</script>

{#if preview}
	<div class="alert alert-warning rounded-0 mb-0 text-center" role="status">
		Aperçu du template « {preview.name} »{preview.status === 'draft' ? ' (brouillon)' : ''} : visible uniquement par les administrateurs connectés au builder.
	</div>
{/if}

<div class="container mt-4">
	<Breadcrumbs items={breadcrumb} />
</div>

{#each sections as section, index (index)}
	{#if section.kind === 'block'}
		<BlockRenderer block={section.block} />
	{:else}
		<div class="container mb-5">
			<div class="row g-3 g-lg-5">
				{#if section.side.length > 0}
					<div class="col-12 col-lg-5 order-lg-1">
						<div class="yack-product__side" data-test-product-box>
							{#each section.side as block, i (i)}<BlockRenderer {block} />{/each}
						</div>
					</div>
				{/if}
				{#if section.main.length > 0}
					<div class="col-12 col-lg-7">
						{#each section.main as block, i (i)}<BlockRenderer {block} />{/each}
					</div>
				{/if}
			</div>
		</div>
	{/if}
{/each}
