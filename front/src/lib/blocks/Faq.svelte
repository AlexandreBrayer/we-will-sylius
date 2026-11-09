<script lang="ts">
	import { textEditor } from './editable.js';
	import { parseFaq } from './parse.js';

	let { title = '', items = '' } = $props();
	const faq = $derived(parseFaq(items));
	const edit = textEditor();
</script>

<section class="yack-cms-section container" data-yack-block="faq">
	{#if title}<h2 class="mb-4" {@attach edit('title')}>{title}</h2>{/if}
	<div class="yack-cms-faq">
		{#each faq as item, index (index)}
			<details open={index === 0}>
				<summary {@attach edit('items', { kind: 'faq', item: index, column: 0 })}>{item.question}</summary>
				<p {@attach edit('items', { kind: 'faq', item: index, column: 1 })}>{item.answer}</p>
			</details>
		{/each}
	</div>
</section>
