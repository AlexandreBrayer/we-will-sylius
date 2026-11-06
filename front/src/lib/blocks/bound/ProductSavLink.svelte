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
		<div class="border rounded-3 p-3 mt-4 mb-4 d-flex align-items-center justify-content-between gap-3" data-yack-block="produit-sav">
			<div>
				<div class="fw-bold">{title || 'Une pièce détachée pour cet appareil ?'}</div>
				<div class="small text-body-secondary">
					{text || "Saisissez le numéro de série de l'appareil pour retrouver ses vues éclatées et ses pièces d'origine."}
				</div>
			</div>
			<a class="btn btn-outline-primary text-nowrap" href="/sav">Espace SAV</a>
		</div>
	</ProductFrame>
{:else}
	<EditorNote label="Lien SAV" text="Choisissez un produit d'exemple pour l'aperçu." />
{/if}
