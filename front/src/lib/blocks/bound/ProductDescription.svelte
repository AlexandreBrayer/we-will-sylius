<script lang="ts">
	// produit-description: long description, in an accordion item open by default.
	import { getYackContext } from '../context.js';
	import EditorNote from './EditorNote.svelte';
	import ProductFrame from './ProductFrame.svelte';

	let { title = '' }: { title?: string } = $props();
	const ctx = getYackContext();
	const product = $derived(ctx.product?.product ?? null);
	const id = $props.id();
	let open = $state(true);
</script>

{#if product?.description}
	<ProductFrame>
		<div class="accordion accordion-flat" data-yack-block="produit-description">
			<div class="accordion-item">
				<h2 class="accordion-header">
					<button class="accordion-button" class:collapsed={!open} type="button" aria-expanded={open} aria-controls="{id}-details" onclick={() => (open = !open)}>
						<span class="h5 mb-0 py-2">{title || 'Description'}</span>
					</button>
				</h2>
				<div id="{id}-details" class="accordion-collapse collapse" class:show={open}>
					<div class="accordion-body" data-test-product-details>{product.description}</div>
				</div>
			</div>
		</div>
	</ProductFrame>
{:else}
	<EditorNote label={title || 'Description'} text={product ? "Ce produit n'a pas de description." : "Choisissez un produit d'exemple pour l'aperçu."} />
{/if}
