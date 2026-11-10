<script lang="ts">
	// Basic block « Texte »: plain text, never HTML (Svelte escapes it). Shop: one paragraph per run of lines
	// separated by a blank line, single line breaks kept (`.yack-cms-text`, white-space: pre-line). Builder
	// canvas: the whole text in one element, edited in place as typed (blank lines included); a blank line
	// there is as high as the space between two paragraphs of the shop.
	import { getYackContext } from './context.js';
	import { textEditor } from './editable.js';
	import { parseParagraphs } from './parse.js';

	let { texte = '' } = $props();
	const ctx = getYackContext();
	const edit = textEditor();
	const paragraphs = $derived(parseParagraphs(texte));
</script>

{#if ctx.editor}
	<div class="yack-cms-texte container" data-yack-block="texte"><p class="yack-cms-text" {@attach edit('texte')}>{texte}</p></div>
{:else if paragraphs.length > 0}
	<div class="yack-cms-texte container" data-yack-block="texte">
		{#each paragraphs as paragraph, index (index)}<p class="yack-cms-text">{paragraph}</p>{/each}
	</div>
{/if}
