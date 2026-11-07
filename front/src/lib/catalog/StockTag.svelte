<script lang="ts">
	// Stock tag of the product card and product page, from `stock.level` of the API (no threshold
	// here): en stock (secondary), faible ("Plus que N" with the units available, tertiary), sur
	// commande / indisponible (muted, label of the API).
	import type { ProductStock } from '#lib/types.js';

	let { stock }: { stock: ProductStock } = $props();

	const TAGS = { en_stock: 'secondary', faible: 'tertiary', sur_commande: 'muted', indisponible: 'muted' } as const;
	const text = $derived(stock.level === 'faible' && stock.available !== null ? `Plus que ${stock.available}` : stock.label);
</script>

<span class="yack-tag yack-tag--{TAGS[stock.level]}" data-test-product-stock={stock.level}>{text}</span>
