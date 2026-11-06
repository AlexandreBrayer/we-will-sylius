<script lang="ts">
	// Product blocks are laid out in columns by TemplateView on the shop (side box / main column).
	// In the builder canvas every block stands alone: given the width of its column.
	import type { Snippet } from 'svelte';
	import { getYackContext } from '../context.js';

	let { side = false, children }: { side?: boolean; children: Snippet } = $props();
	const ctx = getYackContext();
</script>

{#if ctx.editor}
	<div class="container py-3">
		<div class="row">
			<div class={side ? 'col-12 col-lg-5 col-xl-4' : 'col-12 col-lg-7 col-xl-8'}>{@render children()}</div>
		</div>
	</div>
{:else}
	{@render children()}
{/if}
