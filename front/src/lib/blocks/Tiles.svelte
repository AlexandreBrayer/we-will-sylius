<script lang="ts">
	// Range tiles (BlockType "tuiles"): `grande-quatre` = the first filled tile large, the next four
	// in 2 × 2; `grille` = 4 per row (2 on mobile). A slot without label is not rendered.
	import { textEditor } from './editable.js';
	import { imageView } from './image.js';
	import { assetUrl, resolveLink } from './parse.js';

	let props: Record<string, string> = $props();
	const edit = textEditor();
	const layout = $derived(props.layout === 'grille' ? 'grille' : 'grande-quatre');
	const tiles = $derived(
		[1, 2, 3, 4, 5]
			.map((i) => ({ slot: i, label: props[`label${i}`] ?? '', image: props[`image${i}`] ?? '', url: resolveLink(props[`url${i}`]), view: imageView(props, `image${i}`, 'yack-cms-tile__image') }))
			.filter((tile) => tile.label !== '')
	);
</script>

<section class="yack-cms-section container" data-yack-block="tuiles">
	{#if props.title}<h2 class="yack-section-title" {@attach edit('title')}>{props.title}</h2>{/if}
	<div class="yack-cms-tiles yack-cms-tiles--{layout}">
		{#each tiles as tile, index (index)}
			<svelte:element
				this={tile.url ? 'a' : 'div'}
				class="yack-cms-tile"
				class:yack-cms-tile--large={layout === 'grande-quatre' && index === 0}
				href={tile.url || undefined}
			>
				{#if tile.image}<img class={tile.view.class} src={assetUrl(tile.image)} alt={tile.view.alt} loading="lazy" />{/if}
				<span class="yack-cms-tile__label" {@attach edit(`label${tile.slot}`)}>{tile.label}</span>
			</svelte:element>
		{/each}
	</div>
</section>
