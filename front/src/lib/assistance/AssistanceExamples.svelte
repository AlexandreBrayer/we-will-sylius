<script lang="ts">
	// Exemples de /assistance qui donnent un résultat (ASSISTANCE_EXAMPLES, tirés de la base livrée) : les modèles dont
	// les manuels répondent aux questions, puis ceux qui n'ont que leurs PDF. Même présentation que les numéros de
	// démonstration de /sav (pastilles). `only` : `questions` ne montre que les premiers (sous « aucun manuel indexé »).
	import { ASSISTANCE_EXAMPLES, EXAMPLE_LABELS, assistanceUrl } from './assistance.js';

	let { title = 'Exemples de démonstration', only = null }: { title?: string; only?: 'questions' | null } = $props();

	const groups = $derived(
		[
			{ label: EXAMPLE_LABELS.questions, items: ASSISTANCE_EXAMPLES.filter((example) => example.questions) },
			{ label: EXAMPLE_LABELS.pdf, items: only === 'questions' ? [] : ASSISTANCE_EXAMPLES.filter((example) => !example.questions) }
		].filter((group) => group.items.length > 0)
	);
</script>

<div class="mt-3" data-test-assistance-examples>
	<p class="small fw-bold mb-2">{title}</p>
	{#each groups as group (group.label)}
		{#if only === null}<p class="small text-body-secondary mb-1">{group.label}</p>{/if}
		<ul class="yack-chips yack-chips--mono mb-3">
			{#each group.items as example (example.query)}
				<li><a class="yack-chip" href={assistanceUrl({ q: example.query })} data-test-assistance-example={example.query}>{example.query}</a></li>
			{/each}
		</ul>
	{/each}
</div>
