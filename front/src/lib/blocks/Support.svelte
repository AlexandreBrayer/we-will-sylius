<script lang="ts">
	// Support cards (BlockType "accompagnement", « Pour vous accompagner » of the maquette): heading,
	// text and « Découvrir » on the left, visual on the right. A card without heading is not rendered.
	import Icon from '#lib/layout/Icon.svelte';
	import { textEditor } from './editable.js';
	import { imageView } from './image.js';
	import { assetUrl, resolveLink } from './parse.js';

	let props: Record<string, string> = $props();
	const edit = textEditor();
	const cards = $derived(
		[1, 2, 3]
			.map((i) => ({
				slot: i,
				heading: props[`heading${i}`] ?? '',
				text: props[`text${i}`] ?? '',
				image: props[`image${i}`] ?? '',
				url: resolveLink(props[`url${i}`]),
				view: imageView(props, `image${i}`)
			}))
			.filter((card) => card.heading !== '')
	);
</script>

<section class="yack-cms-section container" data-yack-block="accompagnement">
	{#if props.title}<h2 class="yack-section-title" {@attach edit('title')}>{props.title}</h2>{/if}
	<div class="yack-cms-support">
		{#each cards as card, index (index)}
			<svelte:element this={card.url ? 'a' : 'div'} class="yack-cms-support__card" href={card.url || undefined}>
				<span class="yack-cms-support__body">
					<span class="yack-cms-support__heading" {@attach edit(`heading${card.slot}`)}>{card.heading}</span>
					{#if card.text}<span class="yack-cms-support__text" {@attach edit(`text${card.slot}`)}>{card.text}</span>{/if}
					{#if card.url}<span class="yack-cms-support__more">Découvrir <Icon name="chevron-right" size="sm" /></span>{/if}
				</span>
				{#if card.image}<span class="yack-cms-support__visual"><img src={assetUrl(card.image)} alt={card.view.alt} loading="lazy" class={card.view.class} /></span>{/if}
			</svelte:element>
		{/each}
	</div>
</section>
