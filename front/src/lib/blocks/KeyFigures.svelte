<script lang="ts">
	import { textEditor } from './editable.js';

	let props: Record<string, string> = $props();
	const edit = textEditor();
	const theme = $derived(props.theme || 'clair');
	const figures = $derived(
		[1, 2, 3, 4]
			.map((i) => ({ slot: i, value: props[`value${i}`] ?? '', label: props[`label${i}`] ?? '' }))
			.filter((figure) => figure.value !== '')
	);
</script>

<section class="yack-cms-section yack-cms-section--band yack-cms-figures yack-cms-theme-{theme}" data-yack-block="chiffres-cles">
	<div class="container">
		{#if props.title}<h2 class="yack-cms-figures__title" {@attach edit('title')}>{props.title}</h2>{/if}
		<dl class="yack-cms-figures__list">
			{#each figures as figure, index (index)}
				<div class="yack-cms-figures__item">
					<dt class="yack-cms-figures__value" {@attach edit(`value${figure.slot}`)}>{figure.value}</dt>
					{#if figure.label}<dd class="yack-cms-figures__label" {@attach edit(`label${figure.slot}`)}>{figure.label}</dd>{/if}
				</div>
			{/each}
		</dl>
	</div>
</section>
