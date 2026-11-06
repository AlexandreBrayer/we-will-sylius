<script lang="ts">
	import { parseFaq } from './parse.js';

	let { title = '', items = '' } = $props();
	const faq = $derived(parseFaq(items));
</script>

<section class="yack-cms-section container" data-yack-block="faq">
	{#if title}<h2 class="mb-4">{title}</h2>{/if}
	<div class="yack-cms-faq">
		{#each faq as item, index (index)}
			<details open={index === 0}>
				<summary>{item.question}</summary>
				<p>{item.answer}</p>
			</details>
		{/each}
	</div>
</section>
