<script lang="ts">
	// produit-caracteristiques: Sylius / Yack attributes (power, SCOP, refrigerant…), accordion item
	// collapsed by default.
	import { getYackContext } from '../context.js';
	import EditorNote from './EditorNote.svelte';
	import ProductFrame from './ProductFrame.svelte';

	let { title = '' }: { title?: string } = $props();
	const ctx = getYackContext();
	const product = $derived(ctx.product?.product ?? null);
	const id = $props.id();
	let open = $state(false);
</script>

{#if product && product.attributes.length > 0}
	<ProductFrame>
		<div class="accordion accordion-flat" data-yack-block="produit-caracteristiques">
			<div class="accordion-item">
				<h2 class="accordion-header">
					<button class="accordion-button" class:collapsed={!open} type="button" aria-expanded={open} aria-controls="{id}-attributes" onclick={() => (open = !open)}>
						<span class="h5 mb-0 py-2"><span data-test-tab="attributes">{title || 'Caractéristiques techniques'}</span></span>
					</button>
				</h2>
				<div id="{id}-attributes" class="accordion-collapse collapse" class:show={open}>
					<div class="accordion-body">
						<table class="table table-lg table-list" data-test-product-attributes>
							<tbody>
								{#each product.attributes as attribute (attribute.code)}
									<tr>
										<th scope="row">{attribute.name}</th>
										<td>{attribute.value}</td>
									</tr>
								{/each}
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</ProductFrame>
{:else}
	<EditorNote
		label={title || 'Caractéristiques techniques'}
		text={product ? "Ce produit n'a pas de caractéristiques." : "Choisissez un produit d'exemple pour l'aperçu."}
	/>
{/if}
