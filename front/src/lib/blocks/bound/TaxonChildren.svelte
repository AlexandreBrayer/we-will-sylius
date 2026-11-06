<script lang="ts">
	// categorie-sous-categories: cards of the sub-categories (nothing shown on a leaf category).
	import { getYackContext } from '../context.js';
	import EditorNote from './EditorNote.svelte';

	let { title = '' }: { title?: string } = $props();
	const ctx = getYackContext();
	const children = $derived(ctx.taxon?.children ?? []);
</script>

{#if children.length > 0}
	<section class="yack-cms-section container" data-yack-block="categorie-sous-categories">
		<h2 class="mb-5">{title || 'Sous-catégories'}</h2>
		<div class="yack-cms-categories">
			{#each children as child (child.code)}
				<a class="yack-cms-category" href={child.url}>
					<span class="yack-cms-category__kicker">{ctx.taxon?.taxon.name}</span>
					<span class="yack-cms-category__name">{child.name}</span>
					<span class="yack-cms-category__more">Voir les produits →</span>
				</a>
			{/each}
		</div>
	</section>
{:else}
	<EditorNote
		label={title || 'Sous-catégories'}
		text={ctx.taxon ? "Cette catégorie n'a pas de sous-catégorie : le bloc n'apparaît pas sur sa page." : "Choisissez une catégorie d'exemple pour l'aperçu."}
	/>
{/if}
