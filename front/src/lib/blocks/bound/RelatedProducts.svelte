<script lang="ts">
	// produits-associes: Sylius associations of the product, completed by products of its main
	// category (loaded server-side up to the block's limit). Nothing shown without product.
	import ProductGrid from '#lib/catalog/ProductGrid.svelte';
	import { getYackContext } from '../context.js';
	import EditorNote from './EditorNote.svelte';

	let { title = '', limit = '4' }: { title?: string; limit?: string } = $props();
	const ctx = getYackContext();
	const products = $derived((ctx.product?.related ?? []).slice(0, Number(limit) || 4));
</script>

{#if products.length > 0}
	<section class="yack-cms-section container" data-yack-block="produits-associes">
		<h2 class="mb-5">{title || 'Produits associés'}</h2>
		<ProductGrid {products} viewer={ctx.viewer} />
	</section>
{:else}
	<EditorNote
		label={title || 'Produits associés'}
		text={ctx.product ? 'Aucun produit associé à ce produit.' : "Choisissez un produit d'exemple pour l'aperçu."}
	/>
{/if}
