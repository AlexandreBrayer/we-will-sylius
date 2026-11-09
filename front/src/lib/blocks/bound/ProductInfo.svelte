<script lang="ts">
	// produit-infos: brand tag and range, name (page heading), short description and code.
	import { getYackContext } from '../context.js';
	import EditorNote from './EditorNote.svelte';

	const ctx = getYackContext();
	const product = $derived(ctx.product?.product ?? null);
</script>

{#if product}
	<div class="mb-4" data-yack-block="produit-infos">
		{#if product.brand || product.range}
			<div class="yack-product__brand">
				{#if product.brand}<span class="yack-tag yack-tag--tertiary" data-test-product-brand>{product.brand}</span>{/if}
				{#if product.range}<span data-test-product-range>Gamme {product.range}</span>{/if}
			</div>
		{/if}
		<h1 class="yack-product__title text-break" data-test-product-name={product.name}>{product.name}</h1>
		{#if product.shortDescription}<p class="mb-2">{product.shortDescription}</p>{/if}
		<div class="yack-product__ref">Réf. {product.code}</div>
	</div>
{:else}
	<EditorNote label="Informations produit" text="Choisissez un produit d'exemple pour l'aperçu." />
{/if}
