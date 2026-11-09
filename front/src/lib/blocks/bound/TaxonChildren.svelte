<script lang="ts">
	// categorie-sous-categories: the families of the category as pills, over the whole width of the page
	// (aligned with the breadcrumb and the listing). A category with sub-categories: "all" pill (active)
	// then its sub-categories; without: "all" pill back to the parent, then its siblings, itself active
	// and checked. Rule: #lib/blocks/families.ts. Nothing at the top of a section without sub-categories.
	import Icon from '#lib/layout/Icon.svelte';
	import { getYackContext } from '../context.js';
	import { familyPills } from '../families.js';
	import EditorNote from './EditorNote.svelte';

	let { title = '', allLabel = '' }: { title?: string; allLabel?: string } = $props();
	const ctx = getYackContext();
	const pills = $derived(ctx.taxon ? familyPills(ctx.taxon, allLabel) : []);
	const heading = $derived(title || 'Sous-catégories');
</script>

{#if pills.length > 0}
	<nav class="container mb-4" aria-label={heading} data-yack-block="categorie-sous-categories">
		<h2 class="yack-section-title mt-0 mb-3">{heading}</h2>
		<ul class="yack-subcategories">
			{#each pills as pill (pill.url)}
				<li>
					<a href={pill.url} class:is-current={pill.current} aria-current={pill.current ? 'page' : undefined} data-test-family={pill.all ? 'all' : pill.code}>
						{#if pill.current}<Icon name="check" />{/if}{pill.name}
					</a>
				</li>
			{/each}
		</ul>
	</nav>
{:else}
	<EditorNote
		label={heading}
		text={ctx.taxon ? "Cette catégorie n'a ni sous-catégorie ni famille voisine : le bloc n'apparaît pas sur sa page." : "Choisissez une catégorie d'exemple pour l'aperçu."}
	/>
{/if}
