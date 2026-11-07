<script lang="ts">
	let props: Record<string, string> = $props();
	const theme = $derived(props.theme || 'clair');
	const figures = $derived(
		[1, 2, 3, 4]
			.map((i) => ({ value: props[`value${i}`] ?? '', label: props[`label${i}`] ?? '' }))
			.filter((figure) => figure.value !== '')
	);
</script>

<section class="yack-cms-section container" data-yack-block="chiffres-cles">
	<div class="yack-cms-figures yack-cms-theme-{theme}">
		{#if props.title}<h2 class="h3 mb-4">{props.title}</h2>{/if}
		<dl class="yack-cms-figures__list">
			{#each figures as figure, index (index)}
				<div class="yack-cms-figures__item">
					<dt class="yack-cms-figures__value">{figure.value}</dt>
					{#if figure.label}<dd class="yack-cms-figures__label">{figure.label}</dd>{/if}
				</div>
			{/each}
		</dl>
	</div>
</section>
