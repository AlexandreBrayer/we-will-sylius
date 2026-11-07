<script lang="ts">
	// categorie-sous-categories: the sub-categories as pills under the category heading, aligned on
	// the product column of "categorie-produits" (nothing shown on a leaf category).
	import { getYackContext } from '../context.js';
	import EditorNote from './EditorNote.svelte';

	let { title = '' }: { title?: string } = $props();
	const ctx = getYackContext();
	const children = $derived(ctx.taxon?.children ?? []);
</script>

{#if children.length > 0}
	<section class="container mb-4" data-yack-block="categorie-sous-categories">
		<div class="row">
			<div class="col-12 col-lg-9 offset-lg-3">
				<h2 class="yack-section-title mt-0 mb-3">{title || 'Sous-catégories'}</h2>
				<ul class="yack-subcategories">
					{#each children as child (child.code)}
						<li><a href={child.url}>{child.name}</a></li>
					{/each}
				</ul>
			</div>
		</div>
	</section>
{:else}
	<EditorNote
		label={title || 'Sous-catégories'}
		text={ctx.taxon ? "Cette catégorie n'a pas de sous-catégorie : le bloc n'apparaît pas sur sa page." : "Choisissez une catégorie d'exemple pour l'aperçu."}
	/>
{/if}
