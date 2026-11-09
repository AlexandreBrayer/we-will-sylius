<script lang="ts">
	// produit-sav: box linking to the SAV (exploded views and spare parts by serial number). A text set
	// in the template is typed in place in the builder canvas (D8); a default text is not.
	import { getYackContext } from '../context.js';
	import { textEditor } from '../editable.js';
	import EditorNote from './EditorNote.svelte';

	let { title = '', text = '' }: { title?: string; text?: string } = $props();
	const ctx = getYackContext();
	const edit = textEditor();
</script>

{#if ctx.product}
	<div class="yack-product-sav" data-yack-block="produit-sav">
		<div>
			<p class="yack-product-sav__title" {@attach title ? edit('title') : undefined}>{title || 'Une pièce détachée pour cet appareil ?'}</p>
			<p class="yack-product-sav__text" {@attach text ? edit('text') : undefined}>{text || "Saisissez le numéro de série de l'appareil pour retrouver ses vues éclatées et ses pièces d'origine."}</p>
		</div>
		<a class="btn btn-primary text-nowrap" href="/sav">Espace SAV</a>
	</div>
{:else}
	<EditorNote label="Lien SAV" text="Choisissez un produit d'exemple pour l'aperçu." />
{/if}
