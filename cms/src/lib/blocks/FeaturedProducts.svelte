<script lang="ts">
	import { getYackContext } from './context.js';
	import { useCatalog } from './catalog.svelte.js';
	import { parseCodes } from './parse.js';

	let { title = '', subtitle = '', productCodes = '' } = $props();
	const ctx = getYackContext();
	const codes = $derived(parseCodes(productCodes));
	const catalog = useCatalog(ctx, () => ({ products: codes }));
</script>

<section class="yack-cms-section container" data-yack-block="produits">
	{#if title}<h2 class="mb-2">{title}</h2>{/if}
	{#if subtitle}<p class="text-body-secondary mb-5">{subtitle}</p>{/if}
	<div class="products-grid">
		{#each codes as code (code)}
			{@const product = catalog.current?.products[code]}
			{#if product}
				<div data-test-product={code}>
					<a class="yack-cms-product" href={product.url}>
						<div class="mb-4">
							<div class="yack-cms-product__image">
								{#if product.image}<img src={product.image} alt={product.name} loading="lazy" />{/if}
							</div>
						</div>
						<div class="h6 text-break">{product.name}</div>
					</a>
					<div class="small text-body-tertiary mt-1">Connectez-vous pour voir les prix</div>
					<a class="small fw-semibold text-primary" href={product.url}>Voir le produit →</a>
				</div>
			{:else if ctx.editor && catalog.current}
				<div class="small text-danger border rounded p-3">Produit « {code} » introuvable ou inactif</div>
			{/if}
		{/each}
	</div>
</section>
