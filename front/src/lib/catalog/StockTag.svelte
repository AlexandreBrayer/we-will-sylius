<script lang="ts">
	// Stock tag of the product card and product page, from `stock.level` of the API (label of the
	// API): en stock (secondary), rupture de stock (tertiary), plus commercialisée (muted).
	import type { ProductStock } from '#lib/types.js';

	let { stock }: { stock: ProductStock } = $props();

	const TAGS = { en_stock: 'secondary', rupture: 'tertiary', plus_commercialisee: 'muted' } as const;
</script>

<span class="yack-tag yack-tag--{TAGS[stock.level]}" data-test-product-stock={stock.level}>{stock.label}</span>
