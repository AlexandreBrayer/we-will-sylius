<script lang="ts" module>
	/** Shop button class of each look (BlockType::BUTTON_VARIANTS): pills of the charte (_base.scss). */
	const VARIANTS: Record<string, string> = { plein: 'btn-primary', contour: 'btn-outline-primary', lien: 'btn-ghost' };
</script>

<script lang="ts">
	// Basic block « Bouton »: one button of the charte, its label as plain text, its link through resolveLink()
	// (a refused link, `javascript:`…, gives no button rather than a dead one). Alignment: style `alignement` of
	// the wrapper. Builder canvas: an incomplete button stays shown, dashed, with what it lacks.
	import { getYackContext } from './context.js';
	import { textEditor } from './editable.js';
	import { returnLinks } from './links.js';
	import { resolveLink } from './parse.js';

	let { libelle = '', url = '', variante = 'plein' } = $props();
	const ctx = getYackContext();
	const edit = textEditor();
	const link = returnLinks();
	const resolved = $derived(resolveLink(url));
	const variant = $derived(VARIANTS[variante] ?? VARIANTS.plein);
	const missing = $derived(libelle.trim() === '' ? 'Libellé manquant' : url.trim() === '' ? 'Lien manquant' : resolved === '' ? 'Lien refusé' : '');
</script>

{#if missing === ''}
	<div class="yack-cms-bouton container" data-yack-block="bouton"><a class="btn btn-lg {variant}" href={link(resolved)} {@attach edit('libelle')}>{libelle}</a></div>
{:else if ctx.editor}
	<div class="yack-cms-bouton container" data-yack-block="bouton">
		<span class="btn btn-lg {variant} yack-cms-bouton__incomplet" {@attach edit('libelle')}>{libelle}</span>
		<span class="yack-cms-bouton__note">{missing} : le bouton n’apparaît pas sur la boutique.</span>
	</div>
{/if}
