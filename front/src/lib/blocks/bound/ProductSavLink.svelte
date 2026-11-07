<script lang="ts">
	// produit-sav: box linking to the SAV (exploded views and spare parts by serial number).
	import { getYackContext } from '../context.js';
	import EditorNote from './EditorNote.svelte';
	import ProductFrame from './ProductFrame.svelte';

	let { title = '', text = '' }: { title?: string; text?: string } = $props();
	const ctx = getYackContext();
</script>

{#if ctx.product}
	<ProductFrame side>
		<div class="yack-product-sav" data-yack-block="produit-sav">
			<div>
				<p class="yack-product-sav__title">{title || 'Une pièce détachée pour cet appareil ?'}</p>
				<p class="yack-product-sav__text">
					{text || "Saisissez le numéro de série de l'appareil pour retrouver ses vues éclatées et ses pièces d'origine."}
				</p>
			</div>
			<a class="btn btn-primary text-nowrap" href="/sav">Espace SAV</a>
		</div>
	</ProductFrame>
{:else}
	<EditorNote label="Lien SAV" text="Choisissez un produit d'exemple pour l'aperçu." />
{/if}
