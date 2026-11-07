<script lang="ts">
	import { COMPONENTS } from './components.js';
	import { getYackContext } from './context.js';
	import { isVisibleFor } from './parse.js';
	import type { PageBlock } from './types.js';

	let { block }: { block: PageBlock } = $props();
	const ctx = getYackContext();
	const Resolved = $derived(COMPONENTS[block.type] ?? null);
	// Public pages: a block restricted to a profile is not rendered for the others.
	// Builder canvas: always rendered (the audience is a trait of the selected block).
	const visible = $derived(ctx.editor || isVisibleFor(block.props.audience, ctx.viewer.profile));
</script>

{#if Resolved && visible}
	<Resolved {...block.props} />
{/if}
