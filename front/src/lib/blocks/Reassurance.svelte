<script lang="ts">
	import { textEditor } from './editable.js';
	import { PICTOS } from './pictos.js';
	import { titleTag } from './style.js';
	import { blockStyle } from './styleContext.js';

	let props: Record<string, string> = $props();
	const edit = textEditor();
	// Tag of the main title (style `balise`, h2 by default) and its look: styleContext.ts, style.ts titleTag().
	const style = blockStyle();
	const heading = $derived(titleTag(style()));
	const items = $derived(
		[1, 2, 3, 4]
			.map((i) => ({ slot: i, icon: props[`icon${i}`] ?? '', title: props[`title${i}`] ?? '', text: props[`text${i}`] ?? '' }))
			.filter((item) => item.title !== '')
	);
</script>

<section class="yack-cms-section container" data-yack-block="reassurance">
	{#if props.title}<svelte:element this={heading.tag} class={['mb-4', heading.look]} {@attach edit('title')}>{props.title}</svelte:element>{/if}
	<div class="yack-cms-reassurance">
		{#each items as item, index (index)}
			{@const picto = PICTOS[item.icon] ?? PICTOS.garantie}
			<div class="yack-cms-reassurance__item">
				<span class="yack-cms-reassurance__picto">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox={picto.viewBox} width="28" height="28" aria-hidden="true">{@html picto.svg}</svg>
				</span>
				<span class="fw-bold" {@attach edit(`title${item.slot}`)}>{item.title}</span>
				{#if item.text}<span class="small text-body-secondary" {@attach edit(`text${item.slot}`)}>{item.text}</span>{/if}
			</div>
		{/each}
	</div>
</section>
