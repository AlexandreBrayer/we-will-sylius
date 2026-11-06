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
		<div class="mb-5" data-yack-block="produit-documents">
			<h2 class="h5 mb-3">{title || 'Documents'}</h2>
			<ul class="list-group">
				{#each product.documents as document (document.url)}
					<li class="list-group-item d-flex justify-content-between align-items-center">
						<span>{document.label}</span>
						<a class="btn btn-sm btn-outline-primary" href={document.url} target="_blank" rel="noopener">Télécharger</a>
					</li>
				{/each}
			</ul>
		</div>
	</ProductFrame>
{:else}
	<EditorNote
		label={title || 'Documents'}
		text={product
			? "Aucun document pour ce produit : le bloc n'apparaît pas sur sa fiche (documents = images du produit d'un autre type que « main » dans l'admin Sylius)."
			: "Choisissez un produit d'exemple pour l'aperçu."}
	/>
{/if}
