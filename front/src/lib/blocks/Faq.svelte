<script lang="ts">
	import { textEditor } from './editable.js';
	import { parseFaq } from './parse.js';
	import { titleTag } from './style.js';
	import { blockStyle } from './styleContext.js';

	let { title = '', items = '' } = $props();
	const faq = $derived(parseFaq(items));
	const edit = textEditor();
	// Tag of the main title (style `balise`, h2 by default) and its look: styleContext.ts, style.ts titleTag().
	const style = blockStyle();
	const heading = $derived(titleTag(style()));
</script>

<section class="yack-cms-section container" data-yack-block="faq">
	{#if title}<svelte:element this={heading.tag} class={['mb-4', heading.look]} {@attach edit('title')}>{title}</svelte:element>{/if}
	<div class="yack-cms-faq">
		{#each faq as item, index (index)}
			<details open={index === 0}>
				<summary {@attach edit('items', { kind: 'faq', item: index, column: 0 })}>{item.question}</summary>
				<p {@attach edit('items', { kind: 'faq', item: index, column: 1 })}>{item.answer}</p>
			</details>
		{/each}
	</div>
</section>
