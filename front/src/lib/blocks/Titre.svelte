<script lang="ts">
	// Basic block « Titre »: a heading and an optional subtitle, plain text (Svelte escapes it). Tag of the
	// heading: style `balise` (h2 by default), look of an h2 whatever the tag (style.ts titleTag()); size
	// (style `titre`) and alignment (style `alignement`) come from the wrapper (styleToCss).
	import { getYackContext } from './context.js';
	import { textEditor } from './editable.js';
	import { titleTag } from './style.js';
	import { blockStyle } from './styleContext.js';

	let { titre = '', sousTitre = '' } = $props();
	const ctx = getYackContext();
	const edit = textEditor();
	const style = blockStyle();
	const heading = $derived(titleTag(style()));
</script>

<!-- Builder canvas: an empty heading stays shown (placeholder of _cms-blocks.scss) to be typed in place. -->
{#if titre || sousTitre || ctx.editor}
	<div class="yack-cms-titre container" data-yack-block="titre">
		{#if titre || ctx.editor}<svelte:element this={heading.tag} class={['yack-cms-titre__titre', heading.look ?? 'h2']} {@attach edit('titre')}
				>{titre}</svelte:element
			>{/if}{#if sousTitre}<p class="yack-cms-titre__sous-titre lead" {@attach edit('sousTitre')}>{sousTitre}</p>{/if}
	</div>
{/if}
