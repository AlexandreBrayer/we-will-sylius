<script lang="ts">
	import { returnLinks } from './links.js';
	import { resolveLink } from './parse.js';
	import Icon from '#lib/layout/Icon.svelte';
	import { textEditor } from './editable.js';
	import { PICTOS } from './pictos.js';
	import { titleTag } from './style.js';
	import { blockStyle } from './styleContext.js';

	let props: Record<string, string> = $props();
	const edit = textEditor();
	// Tag of the main title (style `balise`, h2 by default) and its look: styleContext.ts, style.ts titleTag().
	const style = blockStyle();
	const heading = $derived(titleTag(style()));
	const link = returnLinks();
	const tiles = $derived(
		[1, 2, 3, 4]
			.map((i) => ({
				slot: i,
				icon: props[`icon${i}`] ?? '',
				label: props[`label${i}`] ?? '',
				text: props[`text${i}`] ?? '',
				url: link(resolveLink(props[`url${i}`])),
				highlight: props[`highlight${i}`] === 'oui'
			}))
			.filter((tile) => tile.label !== '')
	);
</script>

<section class="yack-cms-section container" data-yack-block="acces-rapides">
	{#if props.title}<svelte:element this={heading.tag} class={['yack-section-title', heading.look]} {@attach edit('title')}>{props.title}</svelte:element>{/if}
	<div class="yack-cms-quick">
		{#each tiles as tile, index (index)}
			{@const picto = PICTOS[tile.icon] ?? PICTOS.conseil}
			<svelte:element
				this={tile.url ? 'a' : 'div'}
				class="yack-cms-quick__tile"
				class:yack-cms-quick__tile--highlight={tile.highlight}
				href={tile.url || undefined}
			>
				<span class="yack-cms-quick__picto">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox={picto.viewBox} width="40" height="40" aria-hidden="true">{@html picto.svg}</svg>
				</span>
				<span class="yack-cms-quick__body">
					<span class="yack-cms-quick__label">{tile.label}{#if tile.url}<Icon name="chevron-right" size="sm" />{/if}</span>
					{#if tile.text}<span class="yack-cms-quick__text" {@attach edit(`text${tile.slot}`)}>{tile.text}</span>{/if}
				</span>
			</svelte:element>
		{/each}
	</div>
</section>
