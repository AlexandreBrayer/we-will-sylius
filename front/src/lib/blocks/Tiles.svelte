<script lang="ts">
	// Range tiles (BlockType "tuiles"): `grande-quatre` = the first filled tile large, the next four
	// in 2 × 2; `grille` = 4 per row (2 on mobile). A slot without label is not rendered.
	import { assetUrl, resolveLink } from './parse.js';

	let props: Record<string, string> = $props();
	const layout = $derived(props.layout === 'grille' ? 'grille' : 'grande-quatre');
	const tiles = $derived(
		[1, 2, 3, 4, 5]
			.map((i) => ({ label: props[`label${i}`] ?? '', image: props[`image${i}`] ?? '', url: resolveLink(props[`url${i}`]) }))
			.filter((tile) => tile.label !== '')
	);
</script>

<section class="yack-cms-section container" data-yack-block="tuiles">
	{#if props.title}<h2 class="yack-section-title">{props.title}</h2>{/if}
	<div class="yack-cms-tiles yack-cms-tiles--{layout}">
		{#each tiles as tile, index (index)}
			<svelte:element
				this={tile.url ? 'a' : 'div'}
				class="yack-cms-tile"
				class:yack-cms-tile--large={layout === 'grande-quatre' && index === 0}
				href={tile.url || undefined}
			>
				{#if tile.image}<img class="yack-cms-tile__image" src={assetUrl(tile.image)} alt="" loading="lazy" />{/if}
				<span class="yack-cms-tile__label">{tile.label}</span>
			</svelte:element>
		{/each}
	</div>
</section>
