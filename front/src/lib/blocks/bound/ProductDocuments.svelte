<script lang="ts">
	// produit-documents: downloadable documents of the product (Sylius product images of another
	// type than main / thumbnail: notice, fiche-technique…). Nothing shown without document.
	import { getYackContext } from '../context.js';
	import EditorNote from './EditorNote.svelte';
	import ProductFrame from './ProductFrame.svelte';

	let { title = '' }: { title?: string } = $props();
	const ctx = getYackContext();
	const product = $derived(ctx.product?.product ?? null);
</script>

{#if product && product.documents.length > 0}
	<ProductFrame>
		<section data-yack-block="produit-documents">
			<h2 class="yack-section-title">{title || 'Documents'}</h2>
			<ul class="yack-doclist">
				{#each product.documents as document (document.url)}
					<li>
						<span class="fw-bold">{document.label}</span>
						<a class="btn btn-sm btn-secondary" href={document.url} target="_blank" rel="noopener">Télécharger</a>
					</li>
				{/each}
			</ul>
		</section>
	</ProductFrame>
{:else}
	<EditorNote
		label={title || 'Documents'}
		text={product
			? "Aucun document pour ce produit : le bloc n'apparaît pas sur sa fiche (documents = images du produit d'un autre type que « main » dans l'admin Sylius)."
			: "Choisissez un produit d'exemple pour l'aperçu."}
	/>
{/if}
