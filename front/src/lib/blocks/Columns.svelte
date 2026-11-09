<script lang="ts">
	// Colonnes (layout container): two or three columns of content blocks side by side (stacked below md),
	// in the charte container. Exactly as many columns as the ratio (containers.ts columnsOf).
	import BlockRenderer from './BlockRenderer.svelte';
	import { columnsOf } from './containers.js';
	import { getYackContext } from './context.js';
	import { COLUMNS_LAYOUT, columnsRowClass, layoutMarkup } from './layout.js';
	import type { PageBlock } from './types.js';

	let { block }: { block: PageBlock } = $props();
	const ctx = getYackContext();
	const markup = $derived(layoutMarkup('colonnes', block.style, ctx.editor));
	const columns = $derived(columnsOf(block.columns, block.props.ratio));
</script>

<div class={markup.class} style={markup.style} id={markup.id} data-yack-block="colonnes">
	<div class={COLUMNS_LAYOUT.inner}>
		<div class={columnsRowClass(block.props)}>
			{#each columns as column, index (index)}
				<div class={COLUMNS_LAYOUT.column}>
					{#each column as child, i (i)}<BlockRenderer block={child} />{/each}
				</div>
			{/each}
		</div>
	</div>
</div>
