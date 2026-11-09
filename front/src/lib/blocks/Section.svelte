<script lang="ts">
	// Section (layout container): a common background, text colour, spacings and width around its content,
	// colonnes and free content blocks rendered by BlockRenderer (each keeps its own markup and style).
	import BlockRenderer from './BlockRenderer.svelte';
	import { getYackContext } from './context.js';
	import { layoutMarkup } from './layout.js';
	import type { PageBlock } from './types.js';

	let { block }: { block: PageBlock } = $props();
	const ctx = getYackContext();
	const markup = $derived(layoutMarkup('section', block.style, ctx.editor));
</script>

<div class={markup.class} style={markup.style} id={markup.id} data-yack-block="section">
	{#each block.children ?? [] as child, index (index)}
		<BlockRenderer block={child} />
	{/each}
</div>
