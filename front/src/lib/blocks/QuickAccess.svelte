<script lang="ts">
	import { resolveLink } from './parse.js';
	import Icon from '#lib/layout/Icon.svelte';
	import { textEditor } from './editable.js';
	import { PICTOS } from './pictos.js';

	let props: Record<string, string> = $props();
	const edit = textEditor();
	const tiles = $derived(
		[1, 2, 3, 4]
			.map((i) => ({
				slot: i,
				icon: props[`icon${i}`] ?? '',
				label: props[`label${i}`] ?? '',
				text: props[`text${i}`] ?? '',
				url: resolveLink(props[`url${i}`]),
				highlight: props[`highlight${i}`] === 'oui'
			}))
			.filter((tile) => tile.label !== '')
	);
</script>

<section class="yack-cms-section container" data-yack-block="acces-rapides">
	{#if props.title}<h2 class="yack-section-title" {@attach edit('title')}>{props.title}</h2>{/if}
	<div class="yack-cms-quick">
		{#each tiles as tile, index (index)}
			<svelte:element
				this={tile.url ? 'a' : 'div'}
				class="yack-cms-quick__tile"
				class:yack-cms-quick__tile--highlight={tile.highlight}
				href={tile.url || undefined}
			>
				<span class="yack-cms-quick__picto">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="40" height="40" aria-hidden="true"
						>{@html (PICTOS[tile.icon] ?? PICTOS.conseil).svg}</svg
					>
				</span>
				<span class="yack-cms-quick__body">
					<span class="yack-cms-quick__label">{tile.label}{#if tile.url}<Icon name="chevron-right" size="sm" />{/if}</span>
					{#if tile.text}<span class="yack-cms-quick__text" {@attach edit(`text${tile.slot}`)}>{tile.text}</span>{/if}
				</span>
			</svelte:element>
		{/each}
	</div>
</section>
