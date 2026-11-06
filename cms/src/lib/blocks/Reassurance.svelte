<script lang="ts">
	import { PICTOS } from './pictos.js';

	let props: Record<string, string> = $props();
	const items = $derived(
		[1, 2, 3, 4]
			.map((i) => ({ icon: props[`icon${i}`] ?? '', title: props[`title${i}`] ?? '', text: props[`text${i}`] ?? '' }))
			.filter((item) => item.title !== '')
	);
</script>

<section class="yack-cms-section container" data-yack-block="reassurance">
	{#if props.title}<h2 class="mb-4">{props.title}</h2>{/if}
	<div class="yack-cms-reassurance">
		{#each items as item, index (index)}
			<div class="yack-cms-reassurance__item">
				<span class="yack-cms-reassurance__picto">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="28" height="28" aria-hidden="true"
						>{@html (PICTOS[item.icon] ?? PICTOS.garantie).svg}</svg
					>
				</span>
				<span class="fw-bold">{item.title}</span>
				{#if item.text}<span class="small text-body-secondary">{item.text}</span>{/if}
			</div>
		{/each}
	</div>
</section>
