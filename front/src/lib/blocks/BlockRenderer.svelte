<script lang="ts">
	import { COMPONENTS } from './components.js';
	import { getYackContext } from './context.js';
	import { isVisibleFor } from './parse.js';
	import { styleToCss } from './style.js';
	import type { PageBlock } from './types.js';

	let { block }: { block: PageBlock } = $props();
	const ctx = getYackContext();
	const Resolved = $derived(COMPONENTS[block.type] ?? null);
	// Public pages: a block restricted to a profile is not rendered for the others.
	// Builder canvas: always rendered (the audience is a trait of the selected block).
	const visible = $derived(ctx.editor || isVisibleFor(block.props.audience, ctx.viewer.profile));
	// Style (D6): section.yack-cms-block with modifier classes and validated `--yb-*` custom
	// properties (style.ts). Shop: no wrapper without style, the block renders exactly as before.
	// Builder canvas: always wrapped (yack-cms-block--editor), so that the markup of the block stays
	// the same when a style is set or removed from the panel.
	const css = $derived(styleToCss(block.style));
</script>

{#if Resolved && visible}
	{#if css || ctx.editor}
		<section
			class={ctx.editor ? `${css?.class ?? 'yack-cms-block'} yack-cms-block--editor` : css?.class}
			style={css?.style || undefined}
			id={css?.id ?? undefined}
		>
			<Resolved {...block.props} />
		</section>
	{:else}
		<Resolved {...block.props} />
	{/if}
{/if}
