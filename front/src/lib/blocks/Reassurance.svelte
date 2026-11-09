<script lang="ts">
	import { textEditor } from './editable.js';
	import { PICTOS } from './pictos.js';

	let props: Record<string, string> = $props();
	const edit = textEditor();
	const items = $derived(
		[1, 2, 3, 4]
			.map((i) => ({ slot: i, icon: props[`icon${i}`] ?? '', title: props[`title${i}`] ?? '', text: props[`text${i}`] ?? '' }))
			.filter((item) => item.title !== '')
	);
</script>

<section class="yack-cms-section container" data-yack-block="reassurance">
	{#if props.title}<h2 class="mb-4" {@attach edit('title')}>{props.title}</h2>{/if}
	<div class="yack-cms-reassurance">
		{#each items as item, index (index)}
			{@const picto = PICTOS[item.icon] ?? PICTOS.garantie}
			<div class="yack-cms-reassurance__item">
				<span class="yack-cms-reassurance__picto">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox={picto.viewBox} width="28" height="28" aria-hidden="true">{@html picto.svg}</svg>
				</span>
				<span class="fw-bold" {@attach edit(`title${item.slot}`)}>{item.title}</span>
				{#if item.text}<span class="small text-body-secondary" {@attach edit(`text${item.slot}`)}>{item.text}</span>{/if}
			</div>
		{/each}
	</div>
</section>
