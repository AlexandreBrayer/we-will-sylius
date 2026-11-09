<script lang="ts">
	// produit-description: long description, in an accordion item open by default (<details>: opens
	// and closes without JavaScript).
	import Icon from '#lib/layout/Icon.svelte';
	import { getYackContext } from '../context.js';
	import EditorNote from './EditorNote.svelte';

	let { title = '' }: { title?: string } = $props();
	const ctx = getYackContext();
	const product = $derived(ctx.product?.product ?? null);
</script>

{#if product?.description}
	<details class="yack-accordion" open data-yack-block="produit-description">
		<summary><h2>{title || 'Description'}</h2><Icon name="chevron" /></summary>
		<div class="yack-accordion__body yack-accordion__body--text" data-test-product-details>{product.description}</div>
	</details>
{:else}
	<EditorNote label={title || 'Description'} text={product ? "Ce produit n'a pas de description." : "Choisissez un produit d'exemple pour l'aperçu."} />
{/if}
