<script lang="ts" module>
	/** Heights of the space (BlockType::SPACER_HEIGHTS): steps of the charte, `--yack-space-*`. */
	const HEIGHTS = ['s', 'm', 'l', 'xl'];
</script>

<script lang="ts">
	// Basic block « Espace »: an empty space of a height of the charte, with a fine line across its middle when
	// asked (`filet`). Decorative: hidden from assistive technologies. Builder canvas: hatched and outlined
	// (_cms-blocks.scss) so that it stays visible and selectable.
	let { hauteur = 'm', filet = 'non' } = $props();
	const height = $derived(HEIGHTS.includes(hauteur) ? hauteur : 'm');
</script>

<div class="yack-cms-espace yack-cms-espace--{height} container" data-yack-block="espace" aria-hidden="true">
	{#if filet === 'oui'}<hr class="yack-cms-espace__filet" />{/if}
</div>
