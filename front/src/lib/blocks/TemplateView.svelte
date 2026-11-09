<script lang="ts">
	// Product page / category page rendered through its CMS template: breadcrumb, then the blocks
	// in order, product blocks grouped in the two-column zones of #lib/blocks/layout.ts (main column:
	// gallery, description, attributes, documents; sticky side box: infos, purchase, SAV link), at the
	// proportions of the demo product page; free blocks and the other bound blocks span the page. The
	// builder canvas builds the same zones (#lib/editor/zones.ts).
	import { page as appPage } from '$app/state';
	import type { Breadcrumb, Viewer } from '#lib/types.js';
	import BlockRenderer from './BlockRenderer.svelte';
	import { setYackContext } from './context.js';
	import { layoutRuns, PRODUCT_ZONE } from './layout.js';
	import TemplateBreadcrumb from './TemplateBreadcrumb.svelte';
	import type { Catalog, PageBlock, ProductContext, TaxonContext } from './types.js';

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

	const sections = $derived(layoutRuns(blocks));

	const breadcrumb: Breadcrumb[] = $derived(product?.breadcrumb ?? taxon?.breadcrumb ?? []);
</script>

{#if preview}
	<div class="alert alert-warning rounded-0 mb-0 text-center" role="status">
		Aperçu du template « {preview.name} »{preview.status === 'draft' ? ' (brouillon)' : ''} : visible uniquement par les administrateurs connectés au builder.
	</div>
{/if}

<TemplateBreadcrumb items={breadcrumb} />

{#each sections as section, index (index)}
	{#if section.kind === 'block'}
		<BlockRenderer block={section.block} />
	{:else}
		<div class={PRODUCT_ZONE.zone}>
			<div class={PRODUCT_ZONE.row}>
				{#if section.side.length > 0}
					<div class={PRODUCT_ZONE.side}>
						<div class={PRODUCT_ZONE.sideBox} data-test-product-box>
							{#each section.side as block, i (i)}<BlockRenderer {block} />{/each}
						</div>
					</div>
				{/if}
				{#if section.main.length > 0}
					<div class={PRODUCT_ZONE.main}>
						{#each section.main as block, i (i)}<BlockRenderer {block} />{/each}
					</div>
				{/if}
			</div>
		</div>
	{/if}
{/each}
