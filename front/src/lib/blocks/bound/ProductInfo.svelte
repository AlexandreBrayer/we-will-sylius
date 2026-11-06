<script lang="ts">
	// produit-infos: brand · range, name (page heading), short description and code.
	import { getYackContext } from '../context.js';
	import EditorNote from './EditorNote.svelte';
	import ProductFrame from './ProductFrame.svelte';

	const ctx = getYackContext();
	const product = $derived(ctx.product?.product ?? null);
	const kicker = $derived(product ? [product.brand, product.range].filter(Boolean).join(' · ') : '');
</script>

{#if product}
	<ProductFrame side>
		<div class="mb-4" data-yack-block="produit-infos">
			{#if kicker}<div class="small text-uppercase fw-semibold text-body-secondary mb-1" data-test-product-brand>{kicker}</div>{/if}
			<h1 class="h2 text-break" data-test-product-name={product.name}>{product.name}</h1>
			{#if product.shortDescription}<div class="mb-2">{product.shortDescription}</div>{/if}
			<small class="text-body-tertiary">{product.code}</small>
		</div>
	</ProductFrame>
{:else}
	<EditorNote label="Informations produit" text="Choisissez un produit d'exemple pour l'aperçu." />
{/if}
