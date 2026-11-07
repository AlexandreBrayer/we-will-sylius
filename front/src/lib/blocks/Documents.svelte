<script lang="ts">
	import { parseLinks } from './parse.js';
	import { PICTOS } from './pictos.js';

	let { title = '', text = '', items = '' } = $props();
	const documents = $derived(parseLinks(items));
</script>

<section class="yack-cms-section container" data-yack-block="documents">
	{#if title}<h2 class="mb-3">{title}</h2>{/if}
	{#if text}<p class="yack-cms-text mb-4">{text}</p>{/if}
	<ul class="yack-cms-documents">
		{#each documents as document, index (index)}
			<li>
				<a class="yack-cms-documents__link" href={document.url}>
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"
						>{@html PICTOS.document.svg}</svg
					>
					<span>{document.label}</span>
				</a>
			</li>
		{/each}
	</ul>
</section>
