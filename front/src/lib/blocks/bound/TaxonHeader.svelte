<script lang="ts">
	// categorie-entete: category name (page heading) and, unless disabled, its description; aligned
	// on the product column of "categorie-produits" (cadrage `liste`, as the Sylius product index) or
	// at the left of the page, with the breadcrumb (cadrage `page`).
	import { getYackContext } from '../context.js';
	import EditorNote from './EditorNote.svelte';

	let { description = 'oui', cadrage = 'liste' }: { description?: string; cadrage?: string } = $props();
	const ctx = getYackContext();
	const taxon = $derived(ctx.taxon?.taxon ?? null);
</script>

{#if taxon}
	<div class="container mt-4 mb-4" data-yack-block="categorie-entete">
		<div class="row">
			<div class={cadrage === 'page' ? 'col-12' : 'col-12 col-lg-9 offset-lg-3 col-xl-10 offset-xl-2'}>
				<h1 class="yack-listing__title text-break">{taxon.name}</h1>
				{#if description !== 'non' && taxon.description}<p class="yack-listing__lead mb-0">{taxon.description}</p>{/if}
			</div>
		</div>
	</div>
{:else}
	<EditorNote label="En-tête de catégorie" text="Choisissez une catégorie d'exemple pour l'aperçu." />
{/if}
