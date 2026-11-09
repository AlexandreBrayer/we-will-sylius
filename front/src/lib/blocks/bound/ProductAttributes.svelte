<script lang="ts">
	// produit-caracteristiques: Sylius / Yack attributes (power, SCOP, refrigerant…) in the table of
	// the demo, accordion item collapsed by default (<details>: opens without JavaScript).
	import Icon from '#lib/layout/Icon.svelte';
	import { getYackContext } from '../context.js';
	import { textEditor } from '../editable.js';
	import EditorNote from './EditorNote.svelte';

	let { title = '' }: { title?: string } = $props();
	const ctx = getYackContext();
	const edit = textEditor();
	const product = $derived(ctx.product?.product ?? null);
</script>

{#if product && product.attributes.length > 0}
	<details class="yack-accordion" data-yack-block="produit-caracteristiques">
		<summary><h2 data-test-tab="attributes" {@attach title ? edit('title') : undefined}>{title || 'Caractéristiques techniques'}</h2><Icon name="chevron" /></summary>
		<div class="yack-accordion__body">
			<table class="yack-specs" data-test-product-attributes>
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
	</details>
{:else}
	<EditorNote
		label={title || 'Caractéristiques techniques'}
		text={product ? "Ce produit n'a pas de caractéristiques." : "Choisissez un produit d'exemple pour l'aperçu."}
	/>
{/if}
